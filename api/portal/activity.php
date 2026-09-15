<?php
// api/portal/activity.php
// Actions du COMPTE uniquement (connexion, clé créée/révoquée/supprimée,
// profil modifié, erreurs clientes...). Les appels à l'API externe elle-
// même vivent désormais uniquement dans "Utilisation API" (usage.php) —
// les mélanger ici créait un doublon confus entre les deux onglets.
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
$pdo = getPdo();

$stmt = $pdo->prepare(
    'SELECT id, type, status, message, metadata, ip_address, country, country_code, is_proxy, user_agent, created_at
     FROM api_portal_events WHERE partner_id = ? ORDER BY created_at DESC LIMIT 1000'
);
$stmt->execute([$user['id']]);
portalJson(200, ['ok' => true, 'events' => $stmt->fetchAll()]);