<?php
// api/registry-count.php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAuth();

$pdo = getPdo();
$summary = $pdo->query('SELECT COUNT(*) AS n, MAX(updated_at) AS latest_updated_at FROM immatriculations')->fetch();
$latest = $pdo->query('SELECT id, updated_at FROM immatriculations ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
$total = (int)$summary['n'];

sendJson(200, [
    'ok' => true,
    'total' => $total,
    'latestUpdatedAt' => $summary['latest_updated_at'],
    'latestUpdatedId' => $latest ? (int)$latest['id'] : 0,
]);
