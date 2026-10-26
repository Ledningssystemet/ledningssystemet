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
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Single sign-on providers
    |--------------------------------------------------------------------------
    */
    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect' => env('AZURE_REDIRECT_URI', env('APP_URL').'/oauthcallback'),
        'tenant' => env('AZURE_TENANT_ID', 'common'),
        'proxy' => env('AZURE_PROXY'),
        'graph_url' => env('AZURE_GRAPH_URL', 'https://graph.microsoft.com/v1.0/me'),
        'logout' => env('AZURE_LOGOUT_URL'),
        'scope' => env('AZURE_SCOPE', 'openid,email,profile,offline_access,User.Read'),
        'trust_idp_mfa' => env('AZURE_TRUST_IDP_MFA', false),
        'mfa_acr_values' => array_filter(array_map('trim', explode(',', env('AZURE_MFA_ACR_VALUES', '')))),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'hosted_domain' => env('GOOGLE_HOSTED_DOMAIN'),
        'scope' => env('GOOGLE_SCOPE', 'openid,email,profile'),
        'trust_idp_mfa' => env('GOOGLE_TRUST_IDP_MFA', false),
        'mfa_acr_values' => array_filter(array_map('trim', explode(',', env('GOOGLE_MFA_ACR_VALUES', '')))),
    ],

];
