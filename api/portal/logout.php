<?php
require_once __DIR__ . '/_bootstrap.php';
$user = portalUser();
if ($user) portalAudit($user['id'], 'auth.logout', 'Déconnexion du portail API.');
setcookie(PORTAL_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
portalJson(200, ['ok' => true]);
