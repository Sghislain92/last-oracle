<?php
// api/admin/update-user.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

$session = requireAdmin();
$body = readJsonBody();

$targetId = (string)($body['userId'] ?? '');
if (!$targetId) sendJson(400, ['error' => 'userId requis.']);

$pdo = getPdo();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();
if (!$target) sendJson(404, ['error' => 'Utilisateur introuvable.']);

$updates = [];
$params = [];

if (array_key_exists('role', $body)) {
    $role = (string)$body['role'];
    if (!in_array($role, ['agent', 'admin'], true)) sendJson(400, ['error' => 'Rôle invalide.']);
    if ($target['id'] === $session['userId'] && $role !== 'admin') {
        sendJson(400, ['error' => 'Impossible de retirer vos propres droits administrateur.']);
    }
    $updates[] = 'role = ?';
    $params[] = $role;
}

if (array_key_exists('active', $body)) {
    $active = (bool)$body['active'];
    if ($target['id'] === $session['userId'] && !$active) {
        sendJson(400, ['error' => 'Impossible de désactiver votre propre compte.']);
    }
    $updates[] = 'active = ?';
    $params[] = $active ? 1 : 0;
}

if (!$updates) sendJson(400, ['error' => 'Aucune modification fournie (role et/ou active).']);

$params[] = $targetId;
$pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);

$stmt = $pdo->prepare('SELECT id, matricule, email, nom, prenoms, role, active, last_login, login_count, register_ip, register_user_agent, last_login_ip, last_login_user_agent, created_at FROM users WHERE id = ?');
$stmt->execute([$targetId]);

sendJson(200, ['ok' => true, 'user' => $stmt->fetch()]);