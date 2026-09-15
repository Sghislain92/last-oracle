<?php
// api/portal/usage.php
// Détail complet de l'utilisation réelle de l'API externe (api_key_audit)
// par les clés de ce partenaire — distinct de "activity.php" qui couvre
// les actions du COMPTE portail (connexion, création de clé...), pas les
// appels API eux-mêmes.
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
$pdo = getPdo();
$stmt = $pdo->prepare(
    "SELECT a.id, a.query_type, a.query_value, a.found, a.ip_address, a.country, a.country_code, a.is_proxy, a.user_agent, a.created_at,
            k.id AS key_id, k.label AS key_label, k.key_prefix
     FROM api_key_audit a
     INNER JOIN api_keys k ON k.id = a.api_key_id
     WHERE k.created_by = ?
     ORDER BY a.created_at DESC
     LIMIT 500"
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

// Petit résumé agrégé, utile pour le tableau de bord (par clé, taux de
// succès) sans que le client ait à retélécharger 500 lignes juste pour
// calculer trois chiffres.
$byKey = [];
$totalFound = 0;
foreach ($rows as $r) {
    $kid = $r['key_id'];
    if (!isset($byKey[$kid])) $byKey[$kid] = ['label' => $r['key_label'], 'total' => 0, 'found' => 0];
    $byKey[$kid]['total']++;
    if ((int)$r['found']) { $byKey[$kid]['found']++; $totalFound++; }
}

portalJson(200, [
    'ok' => true,
    'calls' => $rows,
    'summary' => [
        'total' => count($rows),
        'found' => $totalFound,
        'byKey' => array_values($byKey),
    ],
]);