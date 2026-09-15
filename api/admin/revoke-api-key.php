<?php
// api/admin/revoke-api-key.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAdmin();
$body = readJsonBody();
$id = (string)($body['id'] ?? '');
if (!$id) sendJson(400, ['error' => 'id requis.']);

$pdo = getPdo();
$stmt = $pdo->prepare('UPDATE api_keys SET active = 0 WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) sendJson(404, ['error' => 'Clé API introuvable.']);

sendJson(200, ['ok' => true]);