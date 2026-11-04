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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    // Banco de imagens compartilhado (MediaHub): chave de API somente leitura, usada só no servidor.
    'mediahub' => [
        'url' => env('MEDIAHUB_URL'),
        'key' => env('MEDIAHUB_KEY'),
        // Pastas (raiz) que ESTE sistema mostra, separadas por vírgula. Vazio = todas. Ex.: Comidas,Bebidas,Sobremesas
        'folders' => env('MEDIAHUB_FOLDERS'),
    ],

    // Avisos de pedido novo no celular (Web Push). Chaves geradas uma vez: ver .env (VAPID_*)
    // Enderecos usados pela tela de super admin (saude do socket e link das lojas)
    'health' => [
        'echo_url' => env('HEALTH_ECHO_URL', 'http://ppgfood_echo:6001'),
        'loja_url' => env('HEALTH_LOJA_URL', 'https://delivery.comunidadeppg.com.br'),
    ],

    'vapid' => [
        'public' => env('VAPID_PUBLIC_KEY'),
        'private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'https://painel.comunidadeppg.com.br'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
