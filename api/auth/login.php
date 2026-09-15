<?php
// api/auth/login.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

$body = readJsonBody();
$email = trim((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if (!$email || !$password) sendJson(400, ['error' => 'E-mail et mot de passe requis.']);

$pdo = getPdo();
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $err = $pdo->prepare('INSERT INTO auth_errors (id, email, type, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    $err->execute([uuidV4(), $email, 'login', 'Identifiants invalides.', getClientIp(), getClientUserAgent()]);
    sendJson(401, ['error' => 'E-mail ou mot de passe incorrect.']);
}

if (!(int)$user['active']) {
    $err = $pdo->prepare('INSERT INTO auth_errors (id, email, type, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    $err->execute([uuidV4(), $email, 'login', 'Tentative de connexion sur un compte désactivé.', getClientIp(), getClientUserAgent()]);
    sendJson(403, ['error' => 'Ce compte a été désactivé. Contactez un administrateur.']);
}

$pdo->prepare('UPDATE users SET last_login = NOW(3), login_count = login_count + 1, last_login_ip = ?, last_login_user_agent = ? WHERE id = ?')
    ->execute([getClientIp(), getClientUserAgent(), $user['id']]);

$token = createSessionToken(['userId' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);

sendJson(200, [
    'ok' => true,
    'token' => $token,
    'user' => [
        'id' => $user['id'], 'matricule' => $user['matricule'], 'email' => $user['email'],
        'nom' => $user['nom'], 'prenoms' => $user['prenoms'], 'role' => $user['role'],
    ],
]);