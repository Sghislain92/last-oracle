<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAdmin();

$pdo = getPdo();
$rows = $pdo->query('SELECT * FROM auth_errors ORDER BY created_at DESC')->fetchAll();

sendJson(200, ['ok' => true, 'errors' => $rows]);
