<?php
// api/external/vehicle-lookup.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

// Authentification par clé API — jamais par jeton utilisateur. Inclut
// déjà la vérification du statut actif, de la restriction IP éventuelle,
// et la limitation de débit propre à cette clé.
$apiKey = requireApiKey();

$type = $_GET['type'] ?? '';   // 'vin' | 'plate'
$value = $_GET['value'] ?? '';
if (!in_array($type, ['vin', 'plate'], true) || !$value) {
    sendJson(400, ['error' => 'Paramètres requis : type=vin|plate et value.']);
}

$pdo = getPdo();

if ($type === 'vin') {
    $normalized = normalizeVin($value);
    $stmt = $pdo->prepare('SELECT * FROM immatriculations WHERE chassis_norm = ? ORDER BY updated_at DESC');
} else {
    $normalized = normalizePlate($value);
    $stmt = $pdo->prepare('SELECT * FROM immatriculations WHERE immat_norm = ? ORDER BY updated_at DESC');
}
$stmt->execute([$normalized]);
$all = $stmt->fetchAll();

// Même règle que côté app interne : une fiche sans propriétaire renseigné
// n'est jamais un résultat valable, ni un "ancien propriétaire" — c'est un
// artefact d'import incomplet, ignoré purement et simplement.
$usable = array_values(array_filter($all, function ($r) {
    return trim((string)$r['nom_prenom']) !== '';
}));

$found = !empty($usable);

// Traçabilité — quelle clé a demandé quoi, trouvé ou non, depuis où (IP,
// pays, et indicateur VPN/proxy/hébergeur si l'IP appelante en est un —
// utile ici en particulier : un appel API légitime vient normalement du
// SERVEUR d'un partenaire, pas d'un VPN grand public).
$callerIp = getClientIp();
$geo = resolveIpGeo($callerIp);
$pdo->prepare('INSERT INTO api_key_audit (id, api_key_id, query_type, query_value, found, ip_address, country, country_code, is_proxy, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([uuidV4(), $apiKey['id'], $type, $value, $found ? 1 : 0, $callerIp, $geo['country'], $geo['countryCode'], $geo['isProxy'] ? 1 : 0, getClientUserAgent()]);

if (!$found) sendJson(200, ['ok' => true, 'found' => false]);

usort($usable, function ($a, $b) {
    return strtotime($b['updated_at']) <=> strtotime($a['updated_at']);
});
$current = array_shift($usable);

sendJson(200, [
    'ok' => true,
    'found' => true,
    'vehicle' => [
        'owner' => $current['nom_prenom'],
        'plate' => $current['immatriculation'],
        'department' => $current['departement'],
        'vin' => $current['chassis'],
        'status' => $current['statut'],
        'updatedAt' => $current['updated_at'],
    ],
    'previousOwners' => array_map(function ($r) {
        return [
            'owner' => $r['nom_prenom'], 'plate' => $r['immatriculation'],
            'department' => $r['departement'], 'vin' => $r['chassis'],
        ];
    }, $usable),
]);