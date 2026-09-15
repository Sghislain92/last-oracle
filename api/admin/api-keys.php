<?php
// api/admin/api-keys.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
$session = requireAdmin();
$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // La vraie clé n'est JAMAIS renvoyée après sa création — seul son
    // préfixe (8 caractères) reste visible, pour l'identifier dans les
    // journaux sans jamais pouvoir la reconstituer.
    $keys = $pdo->query(
        'SELECT id, label, key_prefix, allowed_ip, active, rate_limit_per_minute, created_by, last_used_at, last_used_ip, request_count, created_at
         FROM api_keys ORDER BY created_at DESC'
    )->fetchAll();
    sendJson(200, ['ok' => true, 'keys' => $keys]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = readJsonBody();
    $label = trim((string)($body['label'] ?? ''));
    if (!$label) sendJson(400, ['error' => 'Un libellé est requis (ex. "MotoScan BJ - production").']);

    $allowedIp = trim((string)($body['allowedIp'] ?? '')) ?: null;
    $rateLimit = max(1, min(1000, (int)($body['rateLimitPerMinute'] ?? 30)));

    // Clé haute entropie (32 octets = 256 bits) — affichée UNE SEULE FOIS
    // ici, jamais retrouvable ensuite (seul son hash SHA-256 est stocké,
    // comme un mot de passe). Si elle est perdue, il faut en régénérer une.
    $rawKey = 'oracle_' . bin2hex(random_bytes(32));
    $hash = hash('sha256', $rawKey);
    $prefix = substr($rawKey, 0, 14);

    $id = uuidV4();
    $pdo->prepare(
        'INSERT INTO api_keys (id, label, key_hash, key_prefix, allowed_ip, rate_limit_per_minute, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$id, $label, $hash, $prefix, $allowedIp, $rateLimit, $session['userId']]);

    sendJson(201, [
        'ok' => true,
        'apiKey' => $rawKey, // dernière fois qu'elle est visible — à copier immédiatement
        'id' => $id,
        'label' => $label,
        'prefix' => $prefix,
    ]);
}

sendJson(405, ['error' => 'Méthode non autorisée.']);