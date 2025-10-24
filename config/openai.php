<?php

return [
    // Clave de API para OpenAI
    'api_key' => env('OPENAI_API_KEY', ''),

    // URL base opcional (por defecto la de OpenAI)
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),

    // Modelo por defecto para las consultas de texto/chat
    'model' => env('OPENAI_MODEL', 'gpt-5'),

    // Timeout para peticiones HTTP (segundos)
    'timeout' => env('OPENAI_TIMEOUT', 60),
];
