<?php
// api/registry-count.php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAuth();

$pdo = getPdo();
$total = (int)$pdo->query('SELECT COUNT(*) AS n FROM immatriculations')->fetch()['n'];

sendJson(200, ['ok' => true, 'total' => $total]);