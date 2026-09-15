<?php
// api/sync/immatriculations.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAuth();
$body = readJsonBody();
$record = $body['record'] ?? $body;

$vin = normalizeVin($record['châssis'] ?? $record['chassis'] ?? '');
$plate = normalizePlate($record['immatriculation'] ?? '');
$ownerNorm = normalizeOwner($record['nom et prénom'] ?? $record['nom_prenom'] ?? '');

if (!$vin && !$plate) sendJson(400, ['error' => 'Châssis ou immatriculation requis.']);

$pdo = getPdo();

// Regroupement par identité du véhicule : priorité au châssis (identifiant
// physique unique), repli sur l'immatriculation si le châssis est absent.
// MySQL fait ça en une requête indexée — plus besoin de charger un
// fragment JSON entier pour vérifier l'existant.
if ($vin) {
    $stmt = $pdo->prepare('SELECT * FROM immatriculations WHERE chassis_norm = ?');
    $stmt->execute([$vin]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM immatriculations WHERE immat_norm = ?');
    $stmt->execute([$plate]);
}
$group = $stmt->fetchAll();

$existing = null;
foreach ($group as $r) {
    if (normalizeOwner($r['nom_prenom']) === $ownerNorm) { $existing = $r; break; }
}

if ($existing) {
    // Même véhicule + même propriétaire déjà connu : on complète
    // uniquement les champs vides, jamais on n'écrase une valeur déjà
    // présente.
    $newPlate = $record['immatriculation'] ?? '';
    $newDept = $record['département'] ?? $record['departement'] ?? '';
    $update = [];
    $params = [];
    if (!trim((string)$existing['immatriculation']) && $newPlate) {
        $update[] = 'immatriculation = ?'; $params[] = $newPlate;
        $update[] = 'immat_norm = ?'; $params[] = normalizePlate($newPlate);
    }
    if (!trim((string)$existing['departement']) && $newDept) { $update[] = 'departement = ?'; $params[] = $newDept; }
    if ($update) {
        $params[] = $existing['id'];
        $pdo->prepare('UPDATE immatriculations SET ' . implode(', ', $update) . ' WHERE id = ?')->execute($params);
    }
    sendJson(201, ['ok' => true, 'added' => false, 'record' => $existing]);
}

// Même véhicule mais propriétaire DIFFÉRENT (ou premier enregistrement) :
// nouvelle ligne d'historique — la revente laisse l'ancienne fiche
// intacte comme preuve.
$stmt = $pdo->prepare(
    'INSERT INTO immatriculations (nom_prenom, immatriculation, immat_norm, departement, chassis, chassis_norm, statut, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    (string)($record['nom et prénom'] ?? $record['nom_prenom'] ?? ''),
    (string)($record['immatriculation'] ?? ''),
    $plate,
    (string)($record['département'] ?? $record['departement'] ?? ''),
    (string)($record['châssis'] ?? $record['chassis'] ?? ''),
    $vin,
    (string)($record['statut'] ?? 'OK'),
    (string)($record['source'] ?? 'direct'),
]);
$newId = $pdo->lastInsertId();
$fresh = $pdo->prepare('SELECT * FROM immatriculations WHERE id = ?');
$fresh->execute([$newId]);

sendJson(201, ['ok' => true, 'added' => true, 'record' => $fresh->fetch()]);