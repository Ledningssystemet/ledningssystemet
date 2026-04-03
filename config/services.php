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

    'google' => [
        'client_id' => env('AUTH_OAUTH_PROVIDER') === 'google' ? env('AUTH_OAUTH_CLIENT_ID') : null,
        'client_secret' => env('AUTH_OAUTH_PROVIDER') === 'google' ? env('AUTH_OAUTH_CLIENT_SECRET') : null,
        'redirect' => env('AUTH_OAUTH_PROVIDER') === 'google' ? env('AUTH_OAUTH_REDIRECT_URI') : null,
        'hosted_domain' => env('AUTH_OAUTH_PROVIDER') === 'google' ? env('AUTH_OAUTH_WORKSPACE_DOMAIN') : null,
    ],

    'graph' => [
        'client_id' => env('AUTH_OAUTH_PROVIDER') === 'microsoft' ? env('AUTH_OAUTH_CLIENT_ID') : null,
        'client_secret' => env('AUTH_OAUTH_PROVIDER') === 'microsoft' ? env('AUTH_OAUTH_CLIENT_SECRET') : null,
        'redirect' => env('AUTH_OAUTH_PROVIDER') === 'microsoft' ? env('AUTH_OAUTH_REDIRECT_URI') : null,
        'tenant_id' => env('AUTH_OAUTH_PROVIDER') === 'microsoft' ? env('AUTH_OAUTH_TENANT_ID', 'common') : 'common',
    ],

];
