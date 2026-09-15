<?php
declare(strict_types=1);
// setup-pdfparser.php — À OUVRIR UNE SEULE FOIS DANS LE NAVIGATEUR, puis
// à supprimer. Installe la bibliothèque PHP pure smalot/pdfparser (pas
// besoin de pdftotext ni de terminal) dans un dossier vendor/ à côté de
// ce fichier. Nécessite exec() — confirmé disponible sur ce compte par
// diagnostic-pdftotext.php.

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

if (!function_exists('exec')) {
    exit("exec() indisponible — installation impossible par ce moyen.\n");
}

$dir = __DIR__;
chdir($dir);
echo "Dossier de travail : {$dir}\n\n";

// 1) Composer disponible directement sur le PATH ?
exec('which composer 2>/dev/null', $out, $code);
$composerBin = $out ? trim(implode('', $out)) : null;

if (!$composerBin) {
    echo "composer introuvable sur le PATH — téléchargement de composer.phar…\n";
    $installer = @file_get_contents('https://getcomposer.org/installer');
    if ($installer === false) {
        exit("Échec du téléchargement de l'installateur Composer. Vérifiez que ce serveur peut sortir vers getcomposer.org (HTTPS).\n");
    }
    file_put_contents($dir . '/composer-setup.php', $installer);
    exec('php ' . escapeshellarg($dir . '/composer-setup.php') . ' --install-dir=' . escapeshellarg($dir) . ' --filename=composer.phar 2>&1', $out2, $code2);
    echo implode("\n", $out2) . "\n";
    @unlink($dir . '/composer-setup.php');
    if (!file_exists($dir . '/composer.phar')) {
        exit("\nÉchec de l'installation de composer.phar — voir le détail ci-dessus.\n");
    }
    $composerCmd = 'php ' . escapeshellarg($dir . '/composer.phar');
} else {
    echo "composer trouvé : {$composerBin}\n";
    $composerCmd = 'composer';
}

// 2) composer require smalot/pdfparser
echo "\nInstallation de smalot/pdfparser…\n";
exec($composerCmd . ' require smalot/pdfparser --no-interaction 2>&1', $out3, $code3);
echo implode("\n", $out3) . "\n";

echo "\n--- Verdict ---\n";
if (file_exists($dir . '/vendor/autoload.php')) {
    echo "OK : vendor/autoload.php présent. La bibliothèque est prête.\n";
    echo "Supprimez ce fichier (setup-pdfparser.php) maintenant — il n'est plus utile et expose des détails du serveur.\n";
} else {
    echo "ÉCHEC : vendor/autoload.php absent malgré tout. Copiez le détail ci-dessus et envoyez-le pour diagnostic.\n";
}