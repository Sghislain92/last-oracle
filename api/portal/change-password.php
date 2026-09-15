<?php
// api/portal/change-password.php
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
$body = portalBody();
$current = (string)($body['currentPassword'] ?? '');
$new = (string)($body['newPassword'] ?? '');
if (strlen($new) < 10) portalJson(400, ['error' => 'Le nouveau mot de passe doit contenir au moins 10 caractères.']);

$pdo = getPdo();
$stmt = $pdo->prepare('SELECT password_hash FROM api_partners WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();
if (!$row || !password_verify($current, $row['password_hash'])) portalJson(401, ['error' => 'Mot de passe actuel incorrect.']);

$pdo->prepare('UPDATE api_partners SET password_hash = ? WHERE id = ?')
    ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

portalAudit($user['id'], 'account.password_changed', 'Mot de passe modifié.');
portalJson(200, ['ok' => true]);