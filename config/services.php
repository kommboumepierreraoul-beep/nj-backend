<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'sender_name' => env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel'))),
        'test_token' => env('BREVO_TEST_TOKEN'),
        // Communication client/fournisseur via WhatsApp (Doc/communication_whatsapp_manuelle.md,
        // §3) : un template Meta pre-approuve par evenement, envoi manuel uniquement (jamais
        // automatise, voir §0/§2.4). Le numero expediteur n'est PAS ici : il vient de
        // company_settings.whatsapp (App\Models\CompanySettings::current()), deja utilise par le
        // module Factures/Proforma pour les memes coordonnees d'entreprise.
        'whatsapp_templates' => [
            'proforma_send' => env('BREVO_WHATSAPP_TEMPLATE_PROFORMA_SEND'),
            'proforma_relance' => env('BREVO_WHATSAPP_TEMPLATE_PROFORMA_RELANCE'),
            'facture_send' => env('BREVO_WHATSAPP_TEMPLATE_FACTURE_SEND'),
            'rfq_send' => env('BREVO_WHATSAPP_TEMPLATE_RFQ_SEND'),
            'purchase_order_send' => env('BREVO_WHATSAPP_TEMPLATE_PURCHASE_ORDER_SEND'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Toujours derive de FRONTEND_URL, jamais de GOOGLE_REDIRECT_URI : ce
        // callback est une route JSON (POST) appelee par la page frontend
        // /auth/google/callback, PAS une page que Google doit ouvrir
        // directement (voir GoogleAuthController::callback). Un
        // GOOGLE_REDIRECT_URI pointant vers nj-backend (ex. .env historique
        // = APP_URL.'/api/auth/google/callback') faisait atterrir le
        // navigateur sur cette route API apres le consentement Google au
        // lieu de la page frontend — corrige le 2026-08-28, voir
        // Doc/frontend_architecture_structure.md § 4.
        'redirect_uri' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/').'/auth/google/callback',
        'allowed_domain' => env('GOOGLE_ALLOWED_DOMAIN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
