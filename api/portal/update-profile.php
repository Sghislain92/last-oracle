<?php
// api/portal/update-profile.php
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
$body = portalBody();
$company = trim((string)($body['companyName'] ?? ''));
$name = trim((string)($body['contactName'] ?? ''));
$email = strtolower(trim((string)($body['email'] ?? '')));
if (mb_strlen($company) < 2 || mb_strlen($company) > 150) portalJson(400, ['error' => 'Le nom d’entreprise doit contenir entre 2 et 150 caractères.']);
if (mb_strlen($name) < 2 || mb_strlen($name) > 160) portalJson(400, ['error' => 'Le nom du contact doit contenir entre 2 et 160 caractères.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) portalJson(400, ['error' => 'Adresse e-mail invalide.']);

$pdo = getPdo();
if ($email !== $user['email']) {
    $exists = $pdo->prepare('SELECT id FROM api_partners WHERE email = ? AND id != ?');
    $exists->execute([$email, $user['id']]);
    if ($exists->fetch()) portalJson(409, ['error' => 'Cette adresse e-mail est déjà utilisée par un autre compte.']);
}

$pdo->prepare('UPDATE api_partners SET company_name = ?, contact_name = ?, email = ? WHERE id = ?')
    ->execute([$company, $name, $email, $user['id']]);

portalAudit($user['id'], 'account.updated', 'Profil du compte partenaire mis à jour.', ['emailChanged' => $email !== $user['email']]);
portalJson(200, ['ok' => true, 'user' => ['id' => $user['id'], 'email' => $email, 'companyName' => $company, 'contactName' => $name]]);