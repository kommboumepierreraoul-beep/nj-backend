<?php

// Verrouillage explicite du CORS (audit_auth_users_njglobaltrade.md, point 5.7,
// priorite 2/3) : en l'absence de ce fichier, Laravel retombe sur la config par
// defaut du framework, qui autorise TOUTES les origines ('*') sur les routes
// api/* — pratique en developpement, dangereux en production puisque n'importe
// quel site pourrait alors appeler l'API avec les identifiants de l'utilisateur
// (si "supports_credentials" etait active). On restreint ici aux origines
// explicitement listees dans CORS_ALLOWED_ORIGINS (ou, a defaut, FRONTEND_URL,
// deja utilise ailleurs dans l'app pour construire les liens des emails).

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000')))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
