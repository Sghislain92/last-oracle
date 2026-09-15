<?php
// api/auth/users.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAdmin();

$pdo = getPdo();
$users = $pdo->query('SELECT id, matricule, email, nom, prenoms, role, active, last_login, login_count, register_ip, register_user_agent, last_login_ip, last_login_user_agent, created_at FROM users ORDER BY created_at DESC')->fetchAll();

sendJson(200, ['ok' => true, 'users' => $users, 'total' => count($users)]);