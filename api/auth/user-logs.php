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
    $rows = $pdo->query('SELECT * FROM tech_logs ORDER BY created_at DESC')->fetchAll();
} else {
    $targetUserId = ($isAdmin && $requestedUserId) ? $requestedUserId : $session['userId'];
    $stmt = $pdo->prepare('SELECT * FROM tech_logs WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$targetUserId]);
    $rows = $stmt->fetchAll();
}

// metadata est stocké en JSON texte en base — on le redécode pour le
// client, qui attend un objet, pas une chaîne.
foreach ($rows as &$row) {
    if (isset($row['metadata']) && is_string($row['metadata'])) {
        $decoded = json_decode($row['metadata'], true);
        $row['metadata'] = $decoded !== null ? $decoded : null;
    }
}
unset($row);

sendJson(200, ['ok' => true, 'logs' => $rows]);
