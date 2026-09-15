<?php
// api/portal/delete-key.php
// Suppression DÉFINITIVE d'une clé — distincte de la révocation
// (revoke-key.php, réversible en théorie côté admin, garde la ligne).
// Ici la clé est réellement effacée. L'historique d'utilisation
// (api_key_audit) n'est PAS supprimé — volontairement : un journal
// d'appels API reste une preuve d'usage qui doit survivre à la
// suppression de la clé qui l'a généré, comme un relevé bancaire
// survit à la fermeture d'une carte.
require_once __DIR__ . '/_bootstrap.php';
$user = requirePortalUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') portalJson(405, ['error' => 'Méthode non autorisée.']);
$body = portalBody(); $id = trim((string)($body['id'] ?? ''));
if (!$id) portalJson(400, ['error' => 'Identifiant de clé requis.']);
$pdo = getPdo();
$labelStmt = $pdo->prepare('SELECT label FROM api_keys WHERE id = ? AND created_by = ?');
$labelStmt->execute([$id, $user['id']]);
$row = $labelStmt->fetch();
if (!$row) portalJson(404, ['error' => 'Clé introuvable.']);
$pdo->prepare('DELETE FROM api_keys WHERE id = ? AND created_by = ?')->execute([$id, $user['id']]);
$fingerprint = portalClientFingerprint($body);
portalAudit($user['id'], 'key.deleted', 'Clé API supprimée définitivement.', array_filter(['keyId' => $id, 'label' => $row['label'], 'clientInfo' => $fingerprint]));
portalJson(200, ['ok' => true]);
