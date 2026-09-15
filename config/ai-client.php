<?php
// config/ai-client.php
declare(strict_types=1);

/**
 * Appelle OpenRouter avec repli automatique : essaie chaque modèle de
 * config/ai.php dans l'ordre, passe au suivant si le modèle actuel
 * échoue (indisponible, en maintenance, etc.) — jamais d'échec total
 * tant qu'il reste un modèle à essayer dans la liste.
 *
 * Retourne le texte de la réponse, ou null si TOUS les modèles ont
 * échoué (jamais d'exception — un échec IA ne doit jamais faire
 * planter une fonctionnalité qui l'utilise, seulement afficher "IA
 * indisponible pour le moment").
 */
function callAiWithFallback(array $messages, int $maxTokens = 600): ?string {
    $config = require __DIR__ . '/ai.php';
    if (!($config['enabled'] ?? false) || empty($config['api_key']) || $config['api_key'] === 'sk-') {
        return null; // clé non configurée — appelant décide quoi afficher
    }

    foreach ($config['model'] as $model) {
        $ch = curl_init($config['base_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode < 200 || $httpCode >= 300) continue; // modèle suivant

        $data = json_decode($raw, true);
        $text = $data['choices'][0]['message']['content'] ?? null;
        if ($text) return trim($text);
        // Réponse reçue mais vide/mal formée : on essaie quand même le
        // modèle suivant plutôt que d'abandonner tout de suite.
    }

    return null; // tous les modèles de la liste ont échoué
}