<?php
declare(strict_types=1);
// api/oracle-lookup.php
//
// Point d'entrée interne à MotoScan, appelé par enregistrement-moto.html
// (même origine, pas de clé exposée). Ce fichier, lui, détient la vraie
// clé API Oracle et l'utilise côté serveur — c'est la seule façon sûre
// de faire : une clé posée dans le JavaScript du navigateur serait
// visible par quiconque ouvre l'inspecteur, quel que soit l'endroit où
// elle est cachée dans le code.
//
// Générez cette clé depuis https://oracle.motoscanbj.com/api-portal/
// (onglet "Mes clés API"), puis remplacez la valeur ci-dessous — mieux
// encore, lisez-la depuis une variable d'environnement plutôt que de
// l'écrire en dur ici.

header('Content-Type: application/json; charset=utf-8');

const ORACLE_API_KEY = 'oracle_1e90b6a7661c4b4a79ef775be446517d7b7385eb543dbbd89d1e864bb3a521b3';
const ORACLE_BASE_URL = 'https://oracle.motoscanbj.com/api/v1/vehicles/lookup';

$type = $_GET['type'] ?? '';
$value = trim((string)($_GET['value'] ?? ''));

if (!in_array($type, ['vin', 'plate'], true) || $value === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Paramètres invalides.']);
    exit;
}

$url = ORACLE_BASE_URL . '?' . http_build_query(['type' => $type, 'value' => $value]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 6,
    CURLOPT_HTTPHEADER => ['X-API-Key: ' . ORACLE_API_KEY],
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Registre Oracle injoignable : ' . $curlError]);
    exit;
}

// On répercute simplement le statut et le corps renvoyés par Oracle —
// l'agent voit exactement la même chose que si l'API avait été appelée
// directement, sans jamais toucher à la clé.
http_response_code($status);
echo $response;