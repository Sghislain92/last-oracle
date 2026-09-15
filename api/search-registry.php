<?php
// api/search-registry.php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAuth();

$type = $_GET['type'] ?? ''; // 'vin' | 'immat'
$value = $_GET['value'] ?? '';
if (!$value) sendJson(400, ['error' => 'Paramètre "value" requis.']);

$pdo = getPdo();

if ($type === 'vin') {
    $normalized = normalizeVin($value);
    $stmt = $pdo->prepare('SELECT * FROM immatriculations WHERE chassis_norm = ? ORDER BY updated_at DESC');
} else {
    $normalized = normalizePlate($value);
    $stmt = $pdo->prepare('SELECT * FROM immatriculations WHERE immat_norm = ? ORDER BY updated_at DESC');
}
$stmt->execute([$normalized]);
$all = $stmt->fetchAll();

// Une fiche sans propriétaire renseigné n'est ni un résultat valable ni
// un "ancien propriétaire" — même règle que côté client précédemment :
// on l'ignore complètement plutôt que d'afficher un "Propriétaire non
// spécifié" trompeur.
$usable = array_values(array_filter($all, function ($r) {
    return trim((string)$r['nom_prenom']) !== '';
}));

if (!$usable) sendJson(200, ['ok' => true, 'found' => false]);

usort($usable, function ($a, $b) {
    return strtotime($b['updated_at']) <=> strtotime($a['updated_at']);
});
$current = array_shift($usable);

sendJson(200, [
    'ok' => true,
    'found' => true,
    'current' => [
        'owner' => $current['nom_prenom'],
        'plate' => $current['immatriculation'],
        'department' => $current['departement'],
        'vin' => $current['chassis'],
    ],
    'previousOwners' => array_map(function ($r) {
        return [
            'owner' => $r['nom_prenom'], 'plate' => $r['immatriculation'],
            'department' => $r['departement'], 'vin' => $r['chassis'],
        ];
    }, $usable),
]);