<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

$session = requireAuth();

$pdo = getPdo();
$stmt = $pdo->prepare('SELECT id, matricule, email, nom, prenoms, role FROM users WHERE id = ?');
$stmt->execute([$session['userId']]);
$user = $stmt->fetch();

if (!$user) sendJson(401, ['error' => 'Session invalide.']);

sendJson(200, ['ok' => true, 'user' => $user]);
