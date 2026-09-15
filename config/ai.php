<?php
// config/ai.php

return [
    // Ta clé API OpenRouter (commence par "sk-or-v1-").
    // Crée-la sur https://openrouter.ai/keys
    'api_key'  => '',

    // Point d'entrée compatible OpenAI.
    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',

    // Essayés dans l'ordre — si le premier échoue ou n'est plus
    // disponible, le suivant prend le relais automatiquement.
    'model' => [
        'openrouter/free',
        'nvidia/nemotron-3-ultra-550b-a55b:free',
        'nvidia/nemotron-3-super-120b-a12b:free',
        'google/gemma-4-31b-it:free',
        'google/gemma-4-26b-a4b-it:free',
        'nvidia/nemotron-3.5-lightning:free',
        'thinkingmachines/inkling:free',
        'thinkingmachines/inkling-small:free',
        'dots-studio/dots-3-note-preview:free',
        'cohere/north-mini-code:free',
        'poolside/laguna-s-2.1:free',
        'poolside/laguna-xs-2.1:free',
        'inclusionai/ling-3.0-flash-fin:free',
        'inclusionai/ling-3.0-flash-sante:free',
        'inclusionai/ling-3.0-flash-vl:free',
        'nex-agi/nex-n2.5-pro:free',
        'nex-agi/nex-n2.5-mini:free',
        'liquid/lfm-2.5-2.6b:free',
        'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
    ],

    'enabled'  => true,
];