<?php
declare(strict_types=1);
/**
 * Script à exécuter UNE SEULE FOIS, en ligne de commande sur ton
 * serveur (ou en local avant de basculer), pour importer les données
 * existantes (les 256 fragments JSON, OU l'ancien fichier unique
 * Immatriculations.json) dans MySQL.
 *
 * Usage :
 *   php import-from-json.php /chemin/vers/data/immatriculations/*.json
 *   php import-from-json.php /chemin/vers/Immatriculations.json
 *
 * Accepte indifféremment un seul gros fichier (l'ancien format) ou
 * plusieurs fragments (le format le plus récent) — peu importe l'ordre
 * ou le nombre de fichiers passés en argument.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$files = array_slice($argv, 1);
if (!$files) {
    fwrite(STDERR, "Usage : php import-from-json.php fichier1.json [fichier2.json ...]\n");
    exit(1);
}

$pdo = getPdo();
$insert = $pdo->prepare(
    'INSERT INTO immatriculations (nom_prenom, immatriculation, departement, chassis, statut, source, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$total = 0;
$pdo->beginTransaction();
try {
    foreach ($files as $file) {
        if (!is_file($file)) {
            fwrite(STDERR, "Ignoré (introuvable) : $file\n");
            continue;
        }
        $records = json_decode(file_get_contents($file), true);
        if (!is_array($records)) {
            fwrite(STDERR, "Ignoré (pas un tableau JSON) : $file\n");
            continue;
        }
        foreach ($records as $r) {
            $createdAt = !empty($r['createdAt']) ? date('Y-m-d H:i:s', strtotime($r['createdAt'])) : date('Y-m-d H:i:s');
            $updatedAt = !empty($r['updatedAt']) ? date('Y-m-d H:i:s', strtotime($r['updatedAt'])) : $createdAt;
            $insert->execute([
                (string)($r['nom et prénom'] ?? ''),
                (string)($r['immatriculation'] ?? ''),
                (string)($r['département'] ?? $r['departement'] ?? ''),
                (string)($r['châssis'] ?? $r['chassis'] ?? ''),
                (string)($r['statut'] ?? 'OK'),
                (string)($r['source'] ?? 'import'),
                $createdAt,
                $updatedAt,
            ]);
            $total++;
        }
        fwrite(STDOUT, "Traité : $file (" . count($records) . " fiches)\n");
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ÉCHEC, rien n'a été inséré : " . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "\nTerminé. $total fiches importées dans MySQL.\n");
