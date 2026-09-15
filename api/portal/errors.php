<?php
// api/portal/errors.php
// Union de deux sources : les événements du compte marqués en erreur
// (api_portal_events, ex. plantage client, mot de passe erroné une fois
// connecté par ailleurs) ET les tentatives de connexion avec un e-mail
// qui n'a jamais eu de session (auth_errors, pas de partner_id possible
// à ce stade) — les deux sont réellement des "erreurs de ce compte", au
// sens large, et doivent apparaître ici plutôt que rester invisibles.
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
$pdo = getPdo();

$fromEvents = $pdo->prepare(
    "SELECT id, type, status, message, metadata, ip_address, country, country_code, is_proxy, user_agent, created_at
     FROM api_portal_events WHERE partner_id = ? AND status = 'error'
     ORDER BY created_at DESC LIMIT 100"
);
$fromEvents->execute([$user['id']]);
$rows = $fromEvents->fetchAll();

$fromAuth = $pdo->prepare(
    "SELECT id, type, 'error' AS status, message, metadata, ip_address, country, country_code, is_proxy, user_agent, created_at
     FROM auth_errors WHERE email = ?
     ORDER BY created_at DESC LIMIT 100"
);
$fromAuth->execute([$user['email']]);
$rows = array_merge($rows, $fromAuth->fetchAll());

usort($rows, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
portalJson(200, ['ok' => true, 'errors' => array_slice($rows, 0, 150)]);