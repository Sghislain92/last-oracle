<?php
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
$pdo = getPdo();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, label, key_prefix, allowed_ip, active, rate_limit_per_minute, expires_at, last_used_at, last_used_ip, request_count, created_at FROM api_keys WHERE created_by = ? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    portalJson(200, ['ok' => true, 'keys' => $stmt->fetchAll()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
$body = portalBody();
$label = trim((string)($body['label'] ?? '')); $allowedIp = trim((string)($body['allowedIp'] ?? '')) ?: null; $rateLimit = max(1, min(1000, (int)($body['rateLimitPerMinute'] ?? 30)));
if (mb_strlen($label) < 2 || mb_strlen($label) > 150) portalJson(400, ['error' => 'Le libellé doit contenir entre 2 et 150 caractères.']);
if ($allowedIp !== null && !filter_var($allowedIp, FILTER_VALIDATE_IP)) portalJson(400, ['error' => 'L’adresse IP autorisée n’est pas valide.']);

// Durée de vie optionnelle — 0/absent = jamais d'expiration (comportement
// historique conservé). Whitelist stricte de durées plutôt qu'un nombre
// de jours arbitraire envoyé par le client : évite une date aberrante
// (ex. 999999 jours) glissée dans une requête forgée à la main.
$allowedTtlDays = [0 => null, 7 => '+7 days', 30 => '+30 days', 90 => '+90 days', 365 => '+365 days'];
$ttlDays = (int)($body['expiresInDays'] ?? 0);
if (!array_key_exists($ttlDays, $allowedTtlDays)) portalJson(400, ['error' => 'Durée de vie invalide.']);
$expiresAt = $allowedTtlDays[$ttlDays] ? (new DateTime($allowedTtlDays[$ttlDays]))->format('Y-m-d H:i:s.v') : null;

$rawKey = 'oracle_' . bin2hex(random_bytes(32)); $id = uuidV4();
$pdo->prepare('INSERT INTO api_keys (id, label, key_hash, key_prefix, allowed_ip, rate_limit_per_minute, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, $label, hash('sha256', $rawKey), substr($rawKey, 0, 14), $allowedIp, $rateLimit, $expiresAt, $user['id']]);
$fingerprint = portalClientFingerprint($body);
portalAudit($user['id'], 'key.created', 'Clé API créée.', array_filter(['keyId' => $id, 'label' => $label, 'rateLimitPerMinute' => $rateLimit, 'expiresAt' => $expiresAt, 'clientInfo' => $fingerprint]));
portalJson(201, ['ok' => true, 'apiKey' => $rawKey, 'id' => $id, 'label' => $label, 'expiresAt' => $expiresAt]);
