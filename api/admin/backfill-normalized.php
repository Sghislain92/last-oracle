<?php
// api/admin/backfill-normalized.php
// Version HTTP, par lots et reprenable, du script CLI
// migration/backfill-normalized.php — celui-ci exigeait un accès
// terminal/SSH, indisponible depuis un usage 100% mobile. Un seul lot
// est traité par appel (rapide, jamais de risque de dépassement de
// délai d'exécution sur hébergement mutualisé) ; le curseur `sinceId`
// permet de reprendre exactement où on s'est arrêté à l'appel suivant —
// même principe que syncRegistryDelta() côté application.
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_time_limit(60);
handleCors();
requireAdmin();

$pdo = getPdo();
$batchSize = 3000;
$sinceId = (int)($_GET['sinceId'] ?? 0);

$total = (int)$pdo->query('SELECT COUNT(*) AS n FROM immatriculations')->fetch()['n'];

$stmt = $pdo->prepare('SELECT id, chassis, immatriculation FROM immatriculations WHERE id > ? ORDER BY id ASC LIMIT ' . $batchSize);
$stmt->execute([$sinceId]);
$rows = $stmt->fetchAll();

$update = $pdo->prepare('UPDATE immatriculations SET chassis_norm = ?, immat_norm = ? WHERE id = ?');
$lastId = $sinceId;
$processed = 0;

if ($rows) {
    $pdo->beginTransaction();
    foreach ($rows as $r) {
        $update->execute([normalizeVin($r['chassis']), normalizePlate($r['immatriculation']), $r['id']]);
        $lastId = (int)$r['id'];
        $processed++;
    }
    $pdo->commit();
}

$remaining = (int)$pdo->query("SELECT COUNT(*) AS n FROM immatriculations WHERE id > $lastId")->fetch()['n'];

sendJson(200, [
    'ok' => true,
    'processedThisBatch' => $processed,
    'lastId' => $lastId,
    'total' => $total,
    'remaining' => $remaining,
    'hasMore' => $remaining > 0,
]);
