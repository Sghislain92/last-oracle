<?php
// diagnostic-pdftotext.php — à supprimer après vérification, ne pas laisser en ligne.
header('Content-Type: text/plain; charset=utf-8');

echo "=== Diagnostic extraction PDF côté serveur ===\n\n";

$shellExecOk = function_exists('shell_exec');
echo "1) shell_exec() existe : " . ($shellExecOk ? "OUI" : "NON") . "\n";

$disabled = (string) ini_get('disable_functions');
$shellExecDesactive = stripos($disabled, 'shell_exec') !== false;
echo "2) shell_exec désactivée par l'hébergeur : " . ($shellExecDesactive ? "OUI (bloquant)" : "NON") . "\n";
if ($disabled) echo "   Fonctions désactivées sur ce compte : {$disabled}\n";

$pdftotextPath = null;
if ($shellExecOk && !$shellExecDesactive) {
    $which = @shell_exec('which pdftotext 2>/dev/null');
    $pdftotextPath = $which ? trim($which) : null;
}
echo "3) pdftotext trouvé : " . ($pdftotextPath ? "OUI ({$pdftotextPath})" : "NON") . "\n";

echo "\n--- Verdict ---\n";
if ($shellExecOk && !$shellExecDesactive && $pdftotextPath) {
    echo "OK : extraction serveur via pdftotext possible directement.\n";
} else {
    echo "Extraction via pdftotext impossible sur ce compte — on passera par une bibliothèque PHP pure (Composer) à la place.\n";
    $composerDispo = function_exists('exec') && !stripos((string) ini_get('disable_functions'), 'exec');
    echo "exec() disponible pour lancer composer : " . ($composerDispo ? "OUI" : "NON — installation manuelle du dossier vendor/ nécessaire") . "\n";
}
