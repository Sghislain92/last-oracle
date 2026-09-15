<?php
require_once __DIR__ . '/_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
$body = portalBody();
$email = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');
$fingerprint = portalClientFingerprint($body);

$pdo = getPdo();
$stmt = $pdo->prepare('SELECT * FROM api_partners WHERE email = ?');
$stmt->execute([$email]);
$partner = $stmt->fetch();

if (!$partner || !password_verify($password, $partner['password_hash'])) {
    if ($partner) {
        // Compte existant, mot de passe erroné — rattaché au partner_id réel.
        portalAudit($partner['id'], 'auth.login_failed', 'Identifiants invalides.', $fingerprint ? ['clientInfo' => $fingerprint] : null, 'error');
    } else {
        // E-mail inconnu — pas de partner_id auquel rattacher l'événement,
        // consigné dans auth_errors (comme le reste de l'app) plutôt que
        // silencieusement ignoré comme c'était le cas avant.
        $ip = getClientIp();
        $geo = resolveIpGeo($ip);
        $pdo->prepare(
            'INSERT INTO auth_errors (id, email, type, message, ip_address, country, country_code, is_proxy, user_agent, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            uuidV4(), $email, 'portal.login_unknown_email', 'Tentative de connexion avec un e-mail inconnu.',
            $ip, $geo['country'], $geo['countryCode'], $geo['isProxy'] ? 1 : 0, getClientUserAgent(),
            $fingerprint ? json_encode(['clientInfo' => $fingerprint], JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
    portalJson(401, ['error' => 'E-mail ou mot de passe incorrect.']);