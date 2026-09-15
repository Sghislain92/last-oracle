<?php
// api/admin/import-immatriculations.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

// Un import massif peut prendre plus que le délai par défaut — sans
// objet ici puisque MySQL est largement assez rapide, mais on garde
// une marge généreuse par sécurité (contrairement à Vercel, cPanel te
// laisse ajuster ça librement).
set_time_limit(120);

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAdmin();
$body = readJsonBody();
$incoming = $body['records'] ?? $body;
if (!is_array($incoming)) sendJson(400, ['error' => 'Champ "records" requis (tableau).']);

$pdo = getPdo();
$added = 0; $enriched = 0; $skipped = 0;

// Toute la fusion dans une seule transaction : soit tout est appliqué
// proprement, soit rien ne l'est en cas d'erreur en cours de route —
// fini les écritures partielles qu'on pouvait avoir avec les fragments
// sur GitHub.
$pdo->beginTransaction();
try {
    // Prépare les requêtes une seule fois, réutilisées pour chaque ligne
    // (rapide même sur des dizaines de milliers de fiches).
    $findByVin = $pdo->prepare('SELECT * FROM immatriculations WHERE chassis_norm = ? LIMIT 20');
    $findByPlate = $pdo->prepare('SELECT * FROM immatriculations WHERE immat_norm = ? LIMIT 20');
    $insert = $pdo->prepare('INSERT INTO immatriculations (nom_prenom, immatriculation, immat_norm, departement, chassis, chassis_norm, statut, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    // immat_norm mis à jour EN MÊME TEMPS que immatriculation, avec le
    // même COALESCE (on ne remplace jamais une valeur déjà présente) —
    // sinon la colonne de recherche se désynchronise silencieusement de
    // la colonne affichée, et une future recherche par plaque échouerait
    // pour cette fiche précise sans qu'on comprenne pourquoi.
    $updateFields = $pdo->prepare(
        'UPDATE immatriculations SET
            nom_prenom = COALESCE(NULLIF(nom_prenom, ""), ?),
            immatriculation = COALESCE(NULLIF(immatriculation, ""), ?),
            immat_norm = COALESCE(NULLIF(immat_norm, ""), ?),
            departement = COALESCE(NULLIF(departement, ""), ?)
         WHERE id = ?'
    );

    foreach ($incoming as $rawIncoming) {
        if (!is_array($rawIncoming)) { $skipped++; continue; }
        $raw = normalizeImportRecord($rawIncoming);
        $vin = normalizeVin($raw['chassis']);
        $plate = normalizePlate($raw['immatriculation']);
        if (!$vin && !$plate) { $skipped++; continue; }
        $ownerNorm = normalizeOwner($raw['nom_prenom']);

        if ($vin) { $findByVin->execute([$vin]); $group = $findByVin->fetchAll(); }
        else { $findByPlate->execute([$plate]); $group = $findByPlate->fetchAll(); }

        // Propriétaire vide : jamais traité comme "différent" (donc
        // jamais comme une revente) — on tente juste de compléter les
        // champs vides d'une fiche existante du groupe.
        if (!$ownerNorm && $group) {
            $target = $group[0];
            $changed = (!trim((string)$target['immatriculation']) && $raw['immatriculation'])
                || (!trim((string)$target['departement']) && $raw['departement']);
            if ($changed) {
                $updateFields->execute([$raw['nom_prenom'], $raw['immatriculation'], $plate, $raw['departement'], $target['id']]);
                $enriched++;
            } else {
                $skipped++;
            }
            continue;
        }

        $existing = null;
        foreach ($group as $r) {
            if (normalizeOwner($r['nom_prenom']) === $ownerNorm) { $existing = $r; break; }
        }

        if ($existing) {
            $exactMatch = normalizeOwner($existing['nom_prenom']) === $ownerNorm
                && normalizePlate($existing['immatriculation']) === $plate
                && trim((string)$existing['departement']) === $raw['departement']
                && normalizeVin($existing['chassis']) === $vin;
            if ($exactMatch) { $skipped++; continue; }

            $changed = (!trim((string)$existing['immatriculation']) && $raw['immatriculation'])
                || (!trim((string)$existing['departement']) && $raw['departement']);
            if ($changed) {
                $updateFields->execute([$raw['nom_prenom'], $raw['immatriculation'], $plate, $raw['departement'], $existing['id']]);
                $enriched++;
            } else {
                $skipped++;
            }
            continue;
        }

        // Véhicule connu mais sans AUCUN propriétaire renseigné sur
        // aucune fiche du groupe (import antérieur incomplet) : on
        // comble cette fiche vide plutôt que d'en créer une nouvelle.
        $emptySlot = null;
        foreach ($group as $r) {
            if (!trim((string)$r['nom_prenom'])) { $emptySlot = $r; break; }
        }
        if ($emptySlot && $ownerNorm) {
            $pdo->prepare(
                'UPDATE immatriculations SET
                    nom_prenom = ?,
                    immatriculation = COALESCE(NULLIF(immatriculation, ""), ?),
                    immat_norm = COALESCE(NULLIF(immat_norm, ""), ?),
                    departement = COALESCE(NULLIF(departement, ""), ?)
                 WHERE id = ?'
            )->execute([$raw['nom_prenom'], $raw['immatriculation'], $plate, $raw['departement'], $emptySlot['id']]);
            $enriched++;
            continue;
        }

        // Même véhicule mais propriétaire différent (revente) ou
        // véhicule totalement inconnu : nouvelle fiche.
        $insert->execute([$raw['nom_prenom'], $raw['immatriculation'], $plate, $raw['departement'], $raw['chassis'], $vin, $raw['statut'], $raw['source']]);
        $added++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    sendJson(500, ['error' => 'Erreur pendant l\'import : ' . $e->getMessage()]);
}

$total = (int)$pdo->query('SELECT COUNT(*) AS n FROM immatriculations')->fetch()['n'];

sendJson(200, ['ok' => true, 'added' => $added, 'enriched' => $enriched, 'skipped' => $skipped, 'total' => $total]);