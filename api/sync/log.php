<?php
// api/sync/log.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

$session = requireAuth();
$body = readJsonBody();
$entry = $body['entry'] ?? $body;

$pdo = getPdo();
$stmt = $pdo->prepare('INSERT INTO tech_logs (id, user_id, type, status, message, metadata, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    (string)($entry['id'] ?? uuidV4()),
    $session['userId'],
    (string)($entry['type'] ?? ''),
    (string)($entry['status'] ?? ''),
    (string)($entry['message'] ?? ''),
    isset($entry['metadata']) ? json_encode($entry['metadata'], JSON_UNESCAPED_UNICODE) : null,
    // Capturé côté serveur, jamais fourni par le client : c'est ce qui
    // rend cette traçabilité fiable, y compris pour identifier quel admin
    // (avec quelle IP/navigateur) a déclenché tel import ou telle action —
    // le client ne peut pas la falsifier puisqu'il ne la fournit pas.
    getClientIp(),
    getClientUserAgent(),
]);

sendJson(201, ['ok' => true]);