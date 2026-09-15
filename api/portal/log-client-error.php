<?php
// api/portal/log-client-error.php
// Reçoit les erreurs JavaScript survenues dans le portail (window.onerror,
// unhandledrejection — voir app.js) pour qu'elles apparaissent réellement
// dans l'onglet "Erreurs" au lieu de rester invisibles dans la seule
// console du navigateur de l'agent.
require_once __DIR__ . '/_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
// Ne journalise que pour un partenaire authentifié — une erreur survenant
// avant connexion reste dans la console du navigateur uniquement (pas de
// partner_id auquel la rattacher côté compte, et pas de quoi justifier
// un nouvel usage d'auth_errors ici, qui est réservé à l'échec de
// connexion lui-même).
$user = portalUser();
if (!$user) portalJson(200, ['ok' => true, 'skipped' => true]);

$body = portalBody();
$message = substr((string)($body['message'] ?? 'Erreur inconnue'), 0, 500);
$stack = substr((string)($body['stack'] ?? ''), 0, 1000);
$url = substr((string)($body['url'] ?? ''), 0, 300);
$fingerprint = portalClientFingerprint($body);

portalAudit(
    $user['id'],
    'client.error',
    $message,
    array_filter(['stack' => $stack, 'url' => $url, 'clientInfo' => $fingerprint]),
    'error'
);
portalJson(200, ['ok' => true]);