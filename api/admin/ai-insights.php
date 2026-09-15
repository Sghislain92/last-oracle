<?php
// api/admin/ai-insights.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/ai-client.php';

handleCors();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendJson(405, ['error' => 'Méthode non autorisée.']);

requireAdmin();
$pdo = getPdo();

// Données complètes des 7 derniers jours — noms, e-mails, messages et
// métadonnées inclus, à la demande explicite de l'administrateur, qui a
// été informé que ces données transitent par un fournisseur IA tiers
// (OpenRouter, route vers divers modèles) et en assume la responsabilité.
$logStmt = $pdo->query(
    "SELECT l.type, l.status, l.message, l.metadata, l.created_at, u.email, u.nom, u.prenoms
     FROM tech_logs l LEFT JOIN users u ON u.id = l.user_id
     WHERE l.created_at > NOW() - INTERVAL 7 DAY
     ORDER BY l.created_at DESC LIMIT 300"
)->fetchAll();

$errorStmt = $pdo->query(
    "SELECT type, email, message, created_at FROM auth_errors
     WHERE created_at > NOW() - INTERVAL 7 DAY
     ORDER BY created_at DESC LIMIT 100"
)->fetchAll();

if (!$logStmt && !$errorStmt) {
    sendJson(200, ['ok' => true, 'summary' => "Aucune activité notable sur les 7 derniers jours.", 'aiUsed' => false]);
}

$prompt = "Tu rédiges un RAPPORT D'INCIDENT technique pour une application de vérification de véhicules à deux roues (Oracle, utilisée par la Police Républicaine du Bénin), à partir des événements bruts des 7 derniers jours ci-dessous.\n\n";
$prompt .= "=== LOGS TECHNIQUES ===\n";
foreach ($logStmt as $r) {
    $prompt .= "- [{$r['created_at']}] {$r['prenoms']} {$r['nom']} ({$r['email']}) — {$r['type']} ({$r['status']}) : {$r['message']}";
    if ($r['metadata']) $prompt .= " | métadonnées : {$r['metadata']}";
    $prompt .= "\n";
}
$prompt .= "\n=== ERREURS D'AUTHENTIFICATION ===\n";
foreach ($errorStmt as $r) $prompt .= "- [{$r['created_at']}] {$r['email']} — {$r['type']} : {$r['message']}\n";

$prompt .= "\n\nRédige un VRAI rapport d'incident structuré, en français, avec ces sections obligatoires :\n";
$prompt .= "1. RÉSUMÉ EXÉCUTIF (2-3 phrases : gravité globale, tendance)\n";
$prompt .= "2. INCIDENTS DÉTAILLÉS (pour chaque type d'erreur récurrente : quoi, qui, quand exactement, combien de fois, hypothèse de cause)\n";
$prompt .= "3. AGENTS À SUIVRE (agents avec un nombre inhabituel de recherches échouées ou d'erreurs — nom complet, e-mail, ce qui a été observé)\n";
$prompt .= "4. ACTIONS RECOMMANDÉES (concrètes, priorisées)\n\n";
$prompt .= "Règles de forme strictes :\n";
$prompt .= "- Mets les éléments importants (noms, dates, nombres, types d'erreur) en gras en utilisant EXACTEMENT la balise HTML <b>...</b> — n'utilise JAMAIS la syntaxe Markdown avec des astérisques (**texte**), elle ne s'affiche pas correctement dans cette interface.\n";
$prompt .= "- N'utilise AUCUN mot anglais : dis \"immatriculation\" (ou \"plaque\") et jamais \"plate\" ou \"plates\", \"châssis\" et non \"chassis\" en anglais dans la phrase, etc.\n";
$prompt .= "- Reste factuel et précis, jamais vague (\"plusieurs erreurs\" → donne le nombre exact et les horodatages).";

$summary = callAiWithFallback([
    ['role' => 'system', 'content' => "Tu rédiges des rapports d'incident techniques précis et structurés, exclusivement en français correct (aucun mot anglais), en utilisant des balises HTML <b> pour le gras — jamais la syntaxe Markdown."],
    ['role' => 'user', 'content' => $prompt],
], 1200);

if ($summary === null) {
    // Clé non configurée ou tous les modèles indisponibles — on renvoie
    // quand même les chiffres bruts, jamais une erreur sèche.
    sendJson(200, [
        'ok' => true,
        'aiUsed' => false,
        'summary' => "Résumé IA indisponible pour le moment (clé non configurée ou modèles hors service). Voici les chiffres bruts.",
        'logs' => $logStmt,
        'authErrors' => $errorStmt,
    ]);
}

// Filet de sécurité : certains modèles glissent parfois vers la syntaxe
// Markdown malgré la consigne — on convertit quand même **texte** en
// <b>texte</b> pour que l'affichage reste correct dans tous les cas.
$summary = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $summary);

sendJson(200, ['ok' => true, 'aiUsed' => true, 'summary' => $summary, 'logs' => $logStmt, 'authErrors' => $errorStmt]);