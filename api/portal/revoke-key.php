<?php
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
$body = portalBody(); $id = trim((string)($body['id'] ?? ''));
if (!$id) portalJson(400, ['error' => 'Identifiant de clé requis.']);
$pdo = getPdo(); $stmt = $pdo->prepare('UPDATE api_keys SET active = 0 WHERE id = ? AND created_by = ?'); $stmt->execute([$id, $user['id']]);
if ($stmt->rowCount() === 0) portalJson(404, ['error' => 'Clé introuvable ou déjà révoquée.']);
$fingerprint = portalClientFingerprint($body);
portalAudit($user['id'], 'key.revoked', 'Clé API révoquée.', array_filter(['keyId' => $id, 'clientInfo' => $fingerprint]));
portalJson(200, ['ok' => true]);
