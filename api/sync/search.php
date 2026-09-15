<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

$session = requireAuth();
$body = readJsonBody();

$record = $body['record'] ?? $body; // tolère les deux formes (avec ou sans enveloppe)
$id = (string)($record['id'] ?? uuidV4());

$pdo = getPdo();
// Upsert : un agent peut renvoyer un enregistrement déjà connu (retry
// après coupure réseau) — on écrase proprement plutôt que dupliquer.
$stmt = $pdo->prepare(
    'INSERT INTO search_history
        (id, user_id, search_type, search_key, vin, status, owner, plate, department, message, from_cache, latitude, longitude, accuracy, samples, searched_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        status = VALUES(status), owner = VALUES(owner), plate = VALUES(plate),
        department = VALUES(department), message = VALUES(message),
        from_cache = VALUES(from_cache), latitude = VALUES(latitude),
        longitude = VALUES(longitude), accuracy = VALUES(accuracy), samples = VALUES(samples)'
);
$stmt->execute([
    $id,
    $session['userId'],
    (string)($record['searchType'] ?? ''),
    (string)($record['searchKey'] ?? ''),
    (string)($record['vin'] ?? ''),
    (string)($record['status'] ?? ''),
    (string)($record['owner'] ?? ''),
    (string)($record['plate'] ?? ''),
    (string)($record['department'] ?? ''),
    (string)($record['message'] ?? ''),
    !empty($record['fromCache']) ? 1 : 0,
    isset($record['latitude']) ? (float)$record['latitude'] : null,
    isset($record['longitude']) ? (float)$record['longitude'] : null,
    isset($record['accuracy']) ? (float)$record['accuracy'] : null,
    (int)($record['samples'] ?? 0),
    !empty($record['searchedAt']) ? date('Y-m-d H:i:s.v', strtotime($record['searchedAt'])) : date('Y-m-d H:i:s.v'),
]);

sendJson(201, ['ok' => true, 'id' => $id]);
