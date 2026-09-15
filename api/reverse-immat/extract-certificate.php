<?php
declare(strict_types=1);
// api/reverse-immat/extract-certificate.php
//
// Version serveur de ce que reverse-immat.html faisait jusqu'ici dans le
// navigateur : télécharger le PDF ANaTT, en extraire le texte, puis
// parser les 5 champs (propriétaire, châssis, matricule, annexe,
// numéro). Le navigateur ne reçoit plus que le JSON final (quelques
// centaines d'octets) au lieu du PDF complet (200-300 Ko) — plus besoin
// de télécharger ni de faire tourner pdf.js sur le téléphone de l'agent
// pour chaque certificat d'un scan en masse.
//
// Protégée par requireAuth() : réservée aux agents Oracle connectés,
// comme le reste de l'API — jusqu'ici reverse-immat n'appliquait cette
// restriction que dans son texte de pied de page, jamais réellement.

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

handleCors();
requireAuth();

$numero = (int)($_GET['numero'] ?? 0);
if ($numero < 1) sendJson(400, ['error' => 'Numéro de certificat invalide.']);

// --- 1) Téléchargement du PDF depuis ANaTT (domaine restreint, comme proxy.php) ---
$target = "https://www.moto.anatt.bj/telechargement/{$numero}";
$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OracleBot/1.0)',
]);
$pdfBinaire = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErreur = curl_error($ch);
curl_close($ch);

if ($pdfBinaire === false) {
    sendJson(502, ['error' => 'Échec de récupération depuis ANaTT : ' . $curlErreur]);
}
if ($httpCode !== 200 || substr($pdfBinaire, 0, 4) !== '%PDF') {
    sendJson(404, ['error' => "Erreur {$httpCode} lors de la récupération du PDF (certificat {$numero})."]);
}

// --- 2) Extraction du texte : bibliothèque PHP pure (smalot/pdfparser),
// pdftotext n'étant pas disponible sur ce compte. Nécessite d'avoir
// exécuté setup-pdfparser.php une fois (voir ce fichier, à supprimer
// après usage) pour que vendor/autoload.php existe dans ce dossier.
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    sendJson(500, ['error' => "La bibliothèque d'extraction PDF n'est pas installée (vendor/autoload.php absent). Exécutez setup-pdfparser.php une fois, puis supprimez-le."]);
}
require_once $autoload;

try {
    $parser = new \Smalot\PdfParser\Parser();
    $document = $parser->parseContent($pdfBinaire);
    $texte = $document->getText();
} catch (\Throwable $e) {
    sendJson(500, ['error' => "Échec de l'extraction PDF (certificat {$numero}) : " . $e->getMessage()]);
}

if (trim((string)$texte) === '') {
    sendJson(500, ['error' => "Extraction vide pour le certificat {$numero}."]);
}

// --- 3) Parsing des champs — port fidèle de parserCertificat() côté JS ---
function nettoyerNomPrenomAnatt(?string $valeur): ?string {
    if ($valeur === null) return null;
    $propre = trim(preg_replace('/\s{2,}/', ' ', str_replace(',', '', $valeur)));
    return $propre !== '' ? $propre : null;
}

function parserCertificatAnatt(string $texteBrut): array {
    $lignes = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $texteBrut)),
        static fn($l) => $l !== ''
    ));
    $texte = implode("\n", $lignes);

    $indexOu = static function (string $pattern) use ($lignes): int {
        foreach ($lignes as $i => $l) if (preg_match($pattern, $l)) return $i;
        return -1;
    };
    $ligneApres = static function (string $pattern) use ($lignes, $indexOu): ?string {
        $i = $indexOu($pattern);
        return ($i >= 0 && $i + 1 < count($lignes)) ? trim($lignes[$i + 1]) : null;
    };

    $proprietaire = null;
    $iProp = $indexOu('/Propri[ée]taire du v[ée]hicule/iu');
    if ($iProp > 0) {
        $j = $iProp - 1;
        while ($j >= 0 && preg_match('/^(M\.?|Mme\.?|Mlle\.?)$/iu', trim($lignes[$j]))) $j--;
        if ($j >= 0) {
            $sansTitre = preg_replace('/^(M\.?|Mme\.?|Mlle\.?)\s*/iu', '', $lignes[$j]);
            $proprietaire = nettoyerNomPrenomAnatt($sansTitre);
        }
    }

    $chassis = $ligneApres('/ch[aâ]ssis est\s*:?\s*$/iu') ?: $ligneApres('/ch[aâ]ssis est\s*:?/iu');
    $matricule = $ligneApres('/sous le matricule\s*:?/iu');

    $annexe = null;
    $departementNonSpecifie =
        preg_match('/est\s+pri[ée]\s+de\s+se\s+rapprocher\s+de\s+l[\'\x{2019}]annexe\s+de/iu', $texte) ||
        preg_match('/pour\s+le\s+retrait\s+de\s+la\s+carte\s+grise\s+et\s+la\s+fixation\s+de\s+la\s+plaque\s+min[ée]ralogique\s*\.?/iu', $texte);
    if ($departementNonSpecifie) {
        $annexe = 'Non spécifié';
    } elseif (preg_match('/annexe de\s+([A-ZÉÈÀÂÊÎÔÛÇ][A-ZÉÈÀÂÊÎÔÛÇ \'\x{2019}\-]+)/u', $texte, $m)) {
        $annexe = trim(preg_replace('/\s+pour\b.*$/us', '', $m[1]));
    }

    $numeroTrouve = null;
    if (preg_match('/N[°ºo]\s*(\d{2,})/iu', $texte, $m)) $numeroTrouve = $m[1];

    return ['proprietaire' => $proprietaire, 'chassis' => $chassis, 'matricule' => $matricule, 'annexe' => $annexe, 'numero' => $numeroTrouve];
}

$champs = parserCertificatAnatt((string)$texte);
$complet = $champs['proprietaire'] && $champs['matricule'] && $champs['annexe'] && $champs['chassis'];

sendJson(200, [
    'ok' => true,
    'numero' => $numero,
    'nomPrenom' => $champs['proprietaire'],
    'immatriculation' => $champs['matricule'],
    'departement' => $champs['annexe'],
    'chassis' => $champs['chassis'],
    'statut' => $complet ? 'OK' : 'Vide',
    // Aperçu du texte brut, uniquement si la ligne reste incomplète —
    // pour diagnostiquer un gabarit ANaTT différent sans retélécharger.
    'apercu' => $complet ? null : mb_substr(preg_replace('/\s+/', ' ', (string)$texte), 0, 200),
]);