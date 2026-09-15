<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAuth();

// Curseur composé (date + id) plutôt qu'une simple date : deux fiches
// peuvent partager le même updated_at à la milliseconde près sur un
// import en masse — sans l'id en repli, certaines seraient sautées ou
// renvoyées en double d'une page à l'autre.
$sinceTs = $_GET['since'] ?? '1970-01-01 00:00:00';
$sinceId = (int)($_GET['sinceId'] ?? 0);
$pageSize = min(5000, max(100, (int)($_GET['limit'] ?? 3000)));

$pdo = getPdo();
$stmt = $pdo->prepare(
    'SELECT id, nom_prenom, immatriculation, departement, chassis, statut, source, created_at, updated_at
     FROM immatriculations
     WHERE (updated_at > ?) OR (updated_at = ? AND id > ?)
     ORDER BY updated_at ASC, id ASC
     LIMIT ' . $pageSize
);
$stmt->execute([$sinceTs, $sinceTs, $sinceId]);
$rows = $stmt->fetchAll();

$hasMore = count($rows) === $pageSize;
$last = $rows ? end($rows) : null;

sendJson(200, [
    'ok' => true,
    'records' => $rows,
    'hasMore' => $hasMore,
    'nextSince' => $last ? $last['updated_at'] : $sinceTs,
    'nextSinceId' => $last ? (int)$last['id'] : $sinceId,
]);
