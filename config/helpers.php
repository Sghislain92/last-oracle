<?php
// config/helpers.php
declare(strict_types=1);

// ----------------------------------------------------------------
// Compatibilité PHP < 8.0 : ces trois fonctions sont natives depuis
// PHP 8.0, mais si ce fichier tourne sur un hébergement dont la
// version réelle diffère du réglage global (fréquent sur mutualisé,
// où la version PHP peut être différente par dossier), on les fournit
// nous-mêmes plutôt que de planter. Sans effet si elles existent déjà.
// ----------------------------------------------------------------
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, 0, strlen($needle)) === $needle;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/**
 * Fonctions communes à tous les endpoints — équivalent PHP de ce que
 * faisaient les fonctions utilitaires partagées dans chaque fichier
 * api/*.js sous Vercel (authHeaders, sendJson, verifySessionToken...).
 */

// À CHANGER impérativement avant mise en production — une longue
// chaîne aléatoire, gardée secrète (jamais dans le dépôt public).
define('SESSION_SECRET', 'CHANGE_MOI_avec_une_longue_chaine_aleatoire_unique');
define('SESSION_TTL_SECONDS', 60 * 60 * 24 * 30); // 30 jours, comme avant

function sendJson(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lit et décode le corps JSON — avec une vraie vérification anti-CSRF
 * adaptée à une API à jeton Bearer (pas de cookie ici, donc le CSRF
 * classique ne s'applique pas directement), mais une faille apparentée
 * existe : un formulaire HTML piégé sur un autre site peut envoyer un
 * corps en Content-Type "text/plain" qui, une fois décodé, ressemble
 * à du JSON valide — un navigateur ne peut PAS fabriquer un
 * Content-Type "application/json" depuis un <form> HTML classique,
 * seulement depuis du JavaScript (fetch/XHR), lui-même soumis aux
 * règles CORS ci-dessous. En exigeant strictement ce Content-Type,
 * on élimine cette classe d'attaque : un simple formulaire piégé ne
 * peut plus jamais atteindre cet endpoint avec un corps exploité.
 */
function readJsonBody(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (!str_starts_with(strtolower(trim($contentType)), 'application/json')) {
        sendJson(415, ['error' => 'Content-Type application/json requis.']);
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        sendJson(400, ['error' => 'Corps de requête JSON invalide.']);
    }
    return $data;
}

function uuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** Signe un jeton de session — même principe HMAC que la version
 *  Vercel (payload base64url + signature), pour que le format des
 *  jetons reste cohérent si jamais les deux coexistent temporairement
 *  pendant la bascule. */
function createSessionToken(array $payload): string {
    $payload['exp'] = (time() + SESSION_TTL_SECONDS) * 1000; // ms, comme côté client
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, SESSION_SECRET);
    return $b64 . '.' . $sig;
}

function verifySessionToken(?string $token): ?array {
    if (!$token || !str_contains($token, '.')) return null;
    [$b64, $sig] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $b64, SESSION_SECRET);
    if (!hash_equals($expected, $sig)) return null;

    $padded = $b64 . str_repeat('=', (4 - strlen($b64) % 4) % 4);
    $json = base64_decode(strtr($padded, '-_', '+/'));
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['exp'])) return null;
    if ($data['exp'] < time() * 1000) return null; // expiré
    return $data;
}

function getSessionFromRequest(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($header, 'Bearer ')) return null;
    return verifySessionToken(substr($header, 7));
}

function requireAuth(): array {
    $session = getSessionFromRequest();
    if (!$session) sendJson(401, ['error' => 'Authentification requise.']);
    // Vérifie que le compte n'a pas été désactivé DEPUIS l'émission du
    // jeton — sinon une désactivation resterait sans effet jusqu'à
    // l'expiration naturelle du token (30 jours).
    $stmt = getPdo()->prepare('SELECT active FROM users WHERE id = ?');
    $stmt->execute([$session['userId']]);
    $row = $stmt->fetch();
    if (!$row || !(int)$row['active']) sendJson(403, ['error' => 'Ce compte a été désactivé.']);
    return $session;
}

function requireAdmin(): array {
    $session = requireAuth();
    if (($session['role'] ?? '') !== 'admin') sendJson(403, ['error' => 'Réservé aux administrateurs.']);
    return $session;
}

/**
 * Authentification pour l'API externe (applications tierces, MotoScan
 * ou autre) — via en-tête X-API-Key, jamais via jeton Bearer utilisateur.
 * Vérifie la clé, son statut actif, une éventuelle restriction IP, ET la
 * limitation de débit propre à cette clé — tout en un seul appel.
 * Termine la requête (403/401/429) si un contrôle échoue ; retourne la
 * ligne api_keys correspondante sinon.
 */
/**
 * Résout pays + indicateur VPN/proxy/hébergeur pour une IP, avec cache
 * (30 jours) pour ne jamais interroger le service externe à chaque
 * requête. Best-effort strict : toute panne du service externe (ou
 * absence de cURL) renvoie simplement des valeurs vides — ne doit JAMAIS
 * faire échouer l'action réelle (connexion, appel API...) qui en dépend.
 *
 * Limite honnête, à ne jamais perdre de vue : ceci identifie l'IP qui se
 * connecte RÉELLEMENT au serveur. Si c'est un VPN, c'est le pays du VPN
 * qui est renvoyé (avec isProxy=true) — il n'y a pas de moyen côté
 * serveur de remonter à l'IP d'origine derrière ce VPN, par nature.
 */
function resolveIpGeo(string $ip): array {
    $empty = ['country' => null, 'countryCode' => null, 'isProxy' => false];
    if (!$ip) return $empty;
    // Adresses privées/locales (réseau interne, dev local) : rien à résoudre.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return $empty;
    }

    $pdo = getPdo();
    $cached = $pdo->prepare('SELECT country, country_code, is_proxy FROM ip_geo_cache WHERE ip = ? AND looked_up_at > (NOW() - INTERVAL 30 DAY)');
    $cached->execute([$ip]);
    if ($row = $cached->fetch()) {
        return ['country' => $row['country'], 'countryCode' => $row['country_code'], 'isProxy' => (bool)$row['is_proxy']];
    }

    $result = $empty;
    if (function_exists('curl_init')) {
        $ch = curl_init("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,proxy,hosting");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OracleGeo/1.0)',
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        if ($resp) {
            $data = json_decode($resp, true);
            if (($data['status'] ?? '') === 'success') {
                $result = [
                    'country' => $data['country'] ?? null,
                    'countryCode' => $data['countryCode'] ?? null,
                    'isProxy' => !empty($data['proxy']) || !empty($data['hosting']),
                ];
            }
        }
    }

    $pdo->prepare(
        'INSERT INTO ip_geo_cache (ip, country, country_code, is_proxy, looked_up_at) VALUES (?, ?, ?, ?, NOW(3))
         ON DUPLICATE KEY UPDATE country = VALUES(country), country_code = VALUES(country_code), is_proxy = VALUES(is_proxy), looked_up_at = VALUES(looked_up_at)'
    )->execute([$ip, $result['country'], $result['countryCode'], $result['isProxy'] ? 1 : 0]);

    return $result;
}

function requireApiKey(): array {
    $rawKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!$rawKey) sendJson(401, ['error' => 'En-tête X-API-Key requis.']);

    $hash = hash('sha256', $rawKey);
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT * FROM api_keys WHERE key_hash = ?');
    $stmt->execute([$hash]);
    $key = $stmt->fetch();

    if (!$key || !(int)$key['active']) sendJson(401, ['error' => 'Clé API invalide ou désactivée.']);
    if ($key['expires_at'] !== null && strtotime((string)$key['expires_at']) < time()) sendJson(401, ['error' => 'Cette clé API a expiré.']);

    $clientIp = getClientIp();
    if ($key['allowed_ip'] && $key['allowed_ip'] !== $clientIp) {
        sendJson(403, ['error' => 'Cette clé API n\'est pas autorisée depuis cette adresse IP.']);
    }

    // Limitation de débit — fenêtre glissante d'une minute, propre à
    // chaque clé. Après l'incident où l'hébergeur a dû limiter tout le
    // site, cette API externe ne doit JAMAIS pouvoir infliger le même
    // sort — la limite est stricte et appliquée avant toute requête coûteuse.
    $windowStart = date('Y-m-d H:i:00');
    $pdo->prepare(
        'INSERT INTO api_key_rate_limit (api_key_id, window_start, request_count)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE request_count = request_count + 1'
    )->execute([$key['id'], $windowStart]);

    $countStmt = $pdo->prepare('SELECT request_count FROM api_key_rate_limit WHERE api_key_id = ? AND window_start = ?');
    $countStmt->execute([$key['id'], $windowStart]);
    $currentCount = (int)($countStmt->fetch()['request_count'] ?? 0);

    if ($currentCount > (int)$key['rate_limit_per_minute']) {
        header('Retry-After: 60');
        sendJson(429, ['error' => 'Limite de requêtes dépassée pour cette clé API (' . $key['rate_limit_per_minute'] . '/minute). Réessayez dans une minute.']);
    }

    $pdo->prepare('UPDATE api_keys SET last_used_at = NOW(3), last_used_ip = ?, request_count = request_count + 1 WHERE id = ?')
        ->execute([$clientIp, $key['id']]);

    return $key;
}

/** Domaines autorisés à appeler cette API en cross-origin. L'app tourne
 *  sur le même domaine que l'API en production (oracle.motoscanbj.com),
 *  donc "*" n'apportait aucun bénéfice fonctionnel — seulement un vrai
 *  risque : n'importe quel site tiers pouvait faire des requêtes
 *  authentifiées avec le jeton d'un agent si jamais celui-ci fuitait
 *  (XSS ailleurs, extension malveillante...). Ajoute ici un domaine si
 *  un jour l'app est servie séparément de l'API (sous-domaine différent).
 */
const ALLOWED_ORIGINS = [
    'https://oracle.motoscanbj.com',
];

function handleCors(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Adresse IP réelle du client — tient compte d'un éventuel proxy/CDN
 *  devant l'hébergement (X-Forwarded-For), avec repli sur REMOTE_ADDR.
 *  Le header étant théoriquement falsifiable par le client lui-même s'il
 *  n'y a PAS de proxy de confiance devant, ce champ reste une donnée
 *  d'investigation/traçabilité, pas une preuve d'identité absolue. */
function getClientIp(): string {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded) {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function getClientUserAgent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

// ---- Normalisation — IDENTIQUE à la logique déjà utilisée côté client
// et dans les anciens endpoints Vercel, pour que rien ne change dans le
// comportement de recherche/dédoublonnage.
function normalizeVin(?string $v): string {
    return strtoupper(preg_replace('/\s+/', '', (string)$v));
}
function normalizePlate(?string $raw): string {
    $stripped = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$raw));
    if (preg_match('/^([0-9]?[A-Z]{2}[0-9]{4})(RB)?$/', $stripped, $m)) {
        return $m[1] . 'RB';
    }
    return $stripped;
}
function normalizeOwner(?string $n): string {
    return strtoupper(trim(preg_replace('/\s+/', ' ', (string)$n)));
}

function normalizeFieldKey(string $k): string {
    // camelCase -> mots séparés, AVANT la mise en minuscule (même bug
    // corrigé que côté Vercel : "nomPrenom" doit devenir "nom prenom").
    $k = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $k);
    $k = mb_strtolower($k, 'UTF-8');
    // Retire les accents (translittération simple, suffisant pour nos clés).
    $k = str_replace(
        ['é', 'è', 'ê', 'ë', 'à', 'â', 'ô', 'î', 'ï', 'û', 'ù', 'ç'],
        ['e', 'e', 'e', 'e', 'a', 'a', 'o', 'i', 'i', 'u', 'u', 'c'],
        $k
    );
    $k = str_replace(['_', '-'], ' ', $k);
    $k = trim(preg_replace('/\s+/', ' ', $k));
    return $k;
}

/** Normalise une ligne brute uploadée (n'importe quelle variante de
 *  noms de colonnes) vers le schéma standard — équivalent PHP de
 *  normalizeImportRecord() côté Vercel. */
function normalizeImportRecord(array $raw): array {
    $byKey = [];
    foreach ($raw as $k => $v) {
        $byKey[normalizeFieldKey((string)$k)] = $v;
    }
    $pick = function (array $keys) use ($byKey): string {
        foreach ($keys as $k) {
            if (isset($byKey[$k]) && trim((string)$byKey[$k]) !== '') {
                return trim((string)$byKey[$k]);
            }
        }
        return '';
    };

    $departement = $pick(['departement', 'department']);
    // Défaut connu côté source (ANaTT) : quand le département n'a pas été
    // renseigné à l'extraction, le champ récupère à la place soit le début
    // de la phrase-type qui suit habituellement le nom du département
    // ("Est prié de se rapprocher de l'annexe de" — sans rien après,
    // puisqu'il n'y avait justement rien à mettre), soit la phrase
    // suivante qui devrait normalement suivre le nom du département
    // ("pour le retrait de la carte grise et la fixation de la plaque
    // minéralogique."). Dans les deux cas, ce n'est pas un vrai nom de
    // département — on le remplace par une valeur honnête plutôt que de
    // laisser passer ce texte parasite tel quel.
    $departementArtefacts = [
        "Est prié de se rapprocher de l'annexe de",
        "pour le retrait de la carte grise et la fixation de la plaque minéralogique.",
    ];
    foreach ($departementArtefacts as $artefact) {
        if (stripos($departement, $artefact) !== false) {
            $departement = 'Non spécifié';
            break;
        }
    }

    // Certains noms extraits contiennent une virgule entre nom et prénom
    // ("BOBLO, JONAS") — on la retire (ainsi que toute autre virgule
    // présente) pour un affichage propre et cohérent avec le reste du
    // registre, qui n'utilise jamais de virgule dans ce champ.
    $nomPrenom = str_replace(',', '', $pick(['nom et prenom', 'nom prenom', 'nom prenoms', 'proprietaire', 'owner', 'nom']));
    $nomPrenom = trim(preg_replace('/\s+/', ' ', $nomPrenom));

    return [
        'nom_prenom' => $nomPrenom,
        'immatriculation' => $pick(['immatriculation', 'plate', 'plaque']),
        'departement' => $departement,
        'chassis' => $pick(['chassis', 'vin']),
        'statut' => $pick(['statut', 'status']) ?: 'OK',
        'source' => $pick(['source']) ?: 'import',
    ];
}