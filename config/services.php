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

    'kommo' => [
        'subdomain'     => env('KOMMO_SUBDOMAIN'),
        'client_id'     => env('KOMMO_CLIENT_ID'),
        'client_secret' => env('KOMMO_CLIENT_SECRET'),
        'redirect_uri'  => env('KOMMO_REDIRECT_URI'),
    ],

    'q10' => [
        'api_url' => env('Q10_API_URL'),
        'api_key'  => env('Q10_API_KEY'),
        'defaults' => [
            'tipo_identificacion' => env('Q10_DEFAULT_TIPO_IDENTIFICACION', '1'),
            'municipio'           => env('Q10_DEFAULT_MUNICIPIO'),
            'barrio'              => env('Q10_DEFAULT_BARRIO'),
            'como_se_entero'      => env('Q10_DEFAULT_COMO_SE_ENTERO', 1),
            'medio_contacto'      => env('Q10_DEFAULT_MEDIO_CONTACTO', 1),
            'numero_asesor'       => env('Q10_DEFAULT_NUMERO_ASESOR'),
        ],
        'campos_personalizados' => [
            'rango_edad' => [
                'id'      => env('Q10_CAMPO_RANGO_EDAD_ID', 1),
                'default' => env('Q10_CAMPO_RANGO_EDAD_DEFAULT', '25-35'),
            ],
        ],
    ],

];
