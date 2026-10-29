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

    /*
     * Entrada pelo Login Único gov.br e pelo Google (OpenID Connect).
     * Desligados até haver credenciais. O endereço de retorno é
     * https://<dominio-da-prefeitura>/login/oidc/{govbr|google}/retorno — cada
     * domínio precisa estar cadastrado no credenciamento.
     *  - gov.br: credenciamento no Ministério da Gestão (homologação em
     *    sso.staging.acesso.gov.br); o "sub" é o CPF.
     *  - Google: projeto no Google Cloud (OAuth client "Web application").
     */
    'govbr' => [
        'ativo'         => (bool) env('GOVBR_ATIVO', false),
        'client_id'     => env('GOVBR_CLIENT_ID'),
        'client_secret' => env('GOVBR_CLIENT_SECRET'),
        'discovery'     => env('GOVBR_DISCOVERY', env('GOVBR_AMBIENTE', 'staging') === 'producao'
            ? 'https://sso.acesso.gov.br/.well-known/openid-configuration'
            : 'https://sso.staging.acesso.gov.br/.well-known/openid-configuration'),
        'escopos'       => env('GOVBR_ESCOPOS', 'openid email profile'),
    ],

    'google' => [
        'ativo'         => (bool) env('GOOGLE_LOGIN_ATIVO', false),
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'discovery'     => env('GOOGLE_DISCOVERY', 'https://accounts.google.com/.well-known/openid-configuration'),
        'escopos'       => 'openid email profile',
    ],

    // Retorno de entrega/falha do provedor de e-mail (TR 4.11.12–13): POST /webhooks/email/{segredo}
    'email_webhook' => ['segredo' => env('EMAIL_WEBHOOK_SECRET')],

];
