<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

$session = requireAuth();
$pdo = getPdo();

$requestedUserId = $_GET['userId'] ?? null;
$isAdmin = ($session['role'] ?? '') === 'admin';

if ($requestedUserId === 'all') {
    if (!$isAdmin) sendJson(403, ['error' => 'Réservé aux administrateurs.']);
    $rows = $pdo->query('SELECT * FROM search_history ORDER BY searched_at DESC')->fetchAll();
} else {
    // Un agent normal ne peut demander que SA propre historique — même si
    // un userId différent est fourni, on l'ignore silencieusement sauf
    // pour un admin (même règle que l'ancien endpoint Vercel).
    $targetUserId = ($isAdmin && $requestedUserId) ? $requestedUserId : $session['userId'];
    $stmt = $pdo->prepare('SELECT * FROM search_history WHERE user_id = ? ORDER BY searched_at DESC');
    $stmt->execute([$targetUserId]);
    $rows = $stmt->fetchAll();
}

sendJson(200, ['ok' => true, 'history' => $rows]);
