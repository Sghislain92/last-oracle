<?php
// api/auth/register.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

$body = readJsonBody();
$email = trim((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');
$nom = trim((string)($body['nom'] ?? ''));
$prenoms = trim((string)($body['prenoms'] ?? ''));
$matricule = trim((string)($body['matricule'] ?? ''));

if (!$email || !$password || !$nom || !$prenoms || !$matricule) {
    sendJson(400, ['error' => 'Tous les champs sont requis.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJson(400, ['error' => 'Adresse e-mail invalide.']);
}
if (strlen($password) < 6) {
    sendJson(400, ['error' => 'Le mot de passe doit contenir au moins 6 caractères.']);
}

$pdo = getPdo();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR matricule = ?');
$stmt->execute([$email, $matricule]);
if ($stmt->fetch()) {
    // Erreur consultable par l'admin — même logique que l'ancien
    // data/auth-errors.json.
    $err = $pdo->prepare('INSERT INTO auth_errors (id, email, type, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    $err->execute([uuidV4(), $email, 'register', 'E-mail ou matricule déjà utilisé.', getClientIp(), getClientUserAgent()]);
    sendJson(409, ['error' => 'Un compte existe déjà avec cet e-mail ou ce matricule.']);
}

// Le premier compte créé avec cet e-mail précis devient automatiquement
// admin — même règle que l'ancienne version (sghislain229@gmail.com).
$role = ($email === 'sghislain229@gmail.com') ? 'admin' : 'agent';

$id = uuidV4();
$hash = password_hash($password, PASSWORD_DEFAULT);
$ip = getClientIp();
$ua = getClientUserAgent();

$stmt = $pdo->prepare('INSERT INTO users (id, matricule, email, password_hash, nom, prenoms, role, register_ip, register_user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$id, $matricule, $email, $hash, $nom, $prenoms, $role, $ip, $ua]);

$token = createSessionToken(['userId' => $id, 'email' => $email, 'role' => $role]);

sendJson(201, [
    'ok' => true,
    'token' => $token,
    'user' => ['id' => $id, 'matricule' => $matricule, 'email' => $email, 'nom' => $nom, 'prenoms' => $prenoms, 'role' => $role],
]);