<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

const PORTAL_COOKIE = 'oracle_portal_session';
const PORTAL_TTL = 60 * 60 * 24 * 30;

function portalJson(int $status, array $data): void { sendJson($status, $data); }
function portalBody(): array { return readJsonBody(); }
function portalCookieOptions(): array { return ['expires' => time() + PORTAL_TTL, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']; }
function portalToken(array $partner): string { return createSessionToken(['portal' => true, 'partnerId' => $partner['id'], 'email' => $partner['email']]); }
function portalUser(): ?array {
    $token = $_COOKIE[PORTAL_COOKIE] ?? '';
    if (!$token) return null;
    $payload = verifySessionToken($token);
    if (!$payload || empty($payload['portal']) || empty($payload['partnerId'])) return null;
    $stmt = getPdo()->prepare('SELECT id, email, company_name, contact_name, active, created_at, last_login_at FROM api_partners WHERE id = ?');
    $stmt->execute([$payload['partnerId']]);
    $partner = $stmt->fetch();
    return ($partner && (int)$partner['active']) ? $partner : null;
}
function requirePortalUser(): array { $user = portalUser(); if (!$user) portalJson(401, ['error' => 'Connexion au portail API requise.']); return $user; }

/**
 * Empreinte navigateur envoyée volontairement par le client (voir
 * app.js) — fuseau horaire, langue, résolution d'écran, plateforme.
 * Toujours facultative : son absence (ancienne version du script,
 * appel direct à l'API) ne doit jamais faire échouer l'action.
 * Validée superficiellement (longueur) avant d'entrer en base — ce sont
 * des valeurs déclaratives fournies par le navigateur, jamais garanties,
 * mais utiles pour la traçabilité, pas pour une décision de sécurité.
 */
function portalClientFingerprint(array $body): ?array {
    $fp = $body['clientInfo'] ?? null;
    if (!is_array($fp)) return null;
    $clean = [
        'timezone' => substr((string)($fp['timezone'] ?? ''), 0, 60),
        'language' => substr((string)($fp['language'] ?? ''), 0, 20),
        'screen' => substr((string)($fp['screen'] ?? ''), 0, 30),
        'platform' => substr((string)($fp['platform'] ?? ''), 0, 100),
    ];
    return array_filter($clean, static fn($v) => $v !== '');
}

/**
 * Journalise une action du compte partenaire — connexion, création de
 * clé, modification de profil, erreur cliente... Résout systématiquement
 * IP + pays + indicateur VPN/proxy/hébergeur (voir resolveIpGeo, best
 * effort, jamais bloquant), et fusionne l'empreinte navigateur du client
 * si elle est fournie dans $metadata['clientInfo'].
 */
function portalAudit(string $partnerId, string $type, string $message, ?array $metadata = null, string $status = 'info'): void {
    $pdo = getPdo();
    $ip = getClientIp();
    $geo = resolveIpGeo($ip);
    $pdo->prepare(
        'INSERT INTO api_portal_events (id, partner_id, type, status, message, metadata, ip_address, country, country_code, is_proxy, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        uuidV4(), $partnerId, $type, $status, $message,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        $ip, $geo['country'], $geo['countryCode'], $geo['isProxy'] ? 1 : 0,
        getClientUserAgent(),
    ]);
}

handleCors();
