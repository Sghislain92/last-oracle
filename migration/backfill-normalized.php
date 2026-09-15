<?php
// migration/backfill-normalized.php
// À exécuter UNE SEULE FOIS, juste après migration-005, en ligne de
// commande : php migration/backfill-normalized.php
//
// Remplit chassis_norm/immat_norm pour toutes les fiches déjà en base,
// par lots de 5000 (mise à jour par clé primaire — rapide, aucun
// balayage de table). Utilise EXACTEMENT les mêmes fonctions PHP que le
// reste de l'application pour garantir zéro divergence.
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$pdo = getPdo();
$batchSize = 5000;
$total = (int)$pdo->query('SELECT COUNT(*) AS n FROM immatriculations')->fetch()['n'];
$done = 0;
$lastId = 0;

fwrite(STDOUT, "Total à traiter : $total\n");

$update = $pdo->prepare('UPDATE immatriculations SET chassis_norm = ?, immat_norm = ? WHERE id = ?');

while (true) {
    $stmt = $pdo->prepare('SELECT id, chassis, immatriculation FROM immatriculations WHERE id > ? ORDER BY id ASC LIMIT ' . $batchSize);
    $stmt->execute([$lastId]);
    $rows = $stmt->fetchAll();
    if (!$rows) break;

    $pdo->beginTransaction();
    foreach ($rows as $r) {
        $update->execute([normalizeVin($r['chassis']), normalizePlate($r['immatriculation']), $r['id']]);
        $lastId = (int)$r['id'];
    }
    $pdo->commit();

    $done += count($rows);
    fwrite(STDOUT, "Traité : $done / $total\n");
}

fwrite(STDOUT, "Terminé.\n");