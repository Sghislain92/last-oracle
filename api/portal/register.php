<?php
require_once __DIR__ . '/_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
$body = portalBody();
$email = strtolower(trim((string)($body['email'] ?? '')));
$company = trim((string)($body['companyName'] ?? ''));
$name = trim((string)($body['contactName'] ?? ''));
$password = (string)($body['password'] ?? '');
$fingerprint = portalClientFingerprint($body);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($company) < 2 || mb_strlen($name) < 2 || strlen($password) < 10) {
    portalJson(400, ['error' => 'Entreprise, nom, e-mail valide et mot de passe de 10 caractères minimum requis.']);
}
$pdo = getPdo();
$stmt = $pdo->prepare('SELECT id FROM api_partners WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) portalJson(409, ['error' => 'Un compte partenaire existe déjà avec cet e-mail.']);

$id = uuidV4();
$pdo->prepare('INSERT INTO api_partners (id, email, company_name, contact_name, password_hash, active) VALUES (?, ?, ?, ?, ?, 1)')
    ->execute([$id, $email, $company, $name, password_hash($password, PASSWORD_DEFAULT)]);
$partner = ['id' => $id, 'email' => $email, 'company_name' => $company, 'contact_name' => $name];
setcookie(PORTAL_COOKIE, portalToken($partner), portalCookieOptions());
portalAudit($id, 'account.created', 'Compte partenaire créé.', $fingerprint ? ['clientInfo' => $fingerprint] : null);
portalJson(201, ['ok' => true, 'user' => $partner]);
