<?php
// api/track-install.php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

// Authentifié si possible (on sait alors QUI a installé), mais on
// accepte aussi un appel sans session — un agent peut installer l'app
// avant même de créer son compte (lien /installer public).
$session = getSessionFromRequest();
$body = readJsonBody();

$pdo = getPdo();

// clientInstallId : identifiant stable généré une seule fois côté
// client (voir index.html, getOrCreateInstallClientId), indépendant de
// toute tentative réseau réussie ou non. Sert de clé d'idempotence :
// "appinstalled" peut légitimement se déclencher plusieurs fois pour
// une même installation (comportement Android/Chrome documenté), et un
// appel peut échouer côté client après que le serveur ait déjà bien
// enregistré la ligne — dans les deux cas, sans cette clé, on se
// retrouvait avec plusieurs lignes pour une seule installation réelle.
// Absente uniquement pour les tout premiers appels envoyés par une
// version d'app antérieure à ce correctif — auquel cas on retombe sur
// le comportement précédent (insertion simple, sans déduplication).
$clientInstallId = isset($body['clientInstallId']) ? substr((string)$body['clientInstallId'], 0, 64) : null;

$stmt = $pdo->prepare(
    'INSERT INTO installations (id, user_id, client_install_id, ip_address, user_agent, platform, device_model, language, screen_size, timezone)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

try {
    $stmt->execute([
        uuidV4(),
        $session['userId'] ?? null,
        $clientInstallId,
        getClientIp(),
        getClientUserAgent(),
        substr((string)($body['platform'] ?? ''), 0, 100),
        substr((string)($body['deviceModel'] ?? ''), 0, 150),
        substr((string)($body['language'] ?? ''), 0, 20),
        substr((string)($body['screen'] ?? ''), 0, 30),
        substr((string)($body['timezone'] ?? ''), 0, 60),
    ]);
    sendJson(201, ['ok' => true]);
} catch (\PDOException $e) {
    // 23000 = violation de contrainte d'intégrité (ici : la contrainte
    // UNIQUE sur client_install_id, voir migration-003). On répond 200
    // "ok, déjà connue" plutôt que 500 : ce n'est pas une erreur du
    // point de vue du client, c'est exactement le comportement attendu
    // d'un second envoi pour la même installation.
    if ($e->getCode() === '23000') {
        sendJson(200, ['ok' => true, 'duplicate' => true]);
    }
    throw $e;
}