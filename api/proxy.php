<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/helpers.php';

handleCors();

$target = $_GET['url'] ?? '';
if (!$target || !str_starts_with($target, 'https://')) {
    sendJson(400, ['error' => 'Paramètre "url" invalide.']);
}

// Restreint volontairement au seul domaine ANaTT — ce proxy ne doit
// jamais devenir un relais ouvert vers n'importe quel site.
$allowedHost = 'anatt.bj'; // À AJUSTER si le domaine réel diffère
$host = parse_url($target, PHP_URL_HOST);
if (!$host || !str_ends_with($host, $allowedHost)) {
    sendJson(403, ['error' => 'Domaine non autorisé pour ce proxy.']);
}

$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OracleBot/1.0)',
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($body === false) {
    sendJson(502, ['error' => 'Erreur du service distant : ' . $error]);
}

http_response_code($httpCode ?: 200);
header('Content-Type: text/html; charset=utf-8');
echo $body;
