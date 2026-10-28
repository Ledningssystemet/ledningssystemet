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
    | Microsoft Entra ID (Azure AD)
    |--------------------------------------------------------------------------
    |
    | Primary SocialiteProvider keys are AZURE_*. To keep backwards
    | compatibility with existing environments, OAUTH_* keys are used as
    | fallback when AZURE_* is not configured.
    |
    */
    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID', env('OAUTH_CLIENTID')),
        'client_secret' => env('AZURE_CLIENT_SECRET', env('OAUTH_SECRET')),
        'redirect' => env('AZURE_REDIRECT_URI', env('OAUTH_REDIRECT_URI', env('APP_URL').'/oauthcallback')),
        'tenant' => env(
            'AZURE_TENANT_ID',
            env(
                'OAUTH_TENANT_ID',
                (static function (): string {
                    $authorizeUrl = env('OAUTH_SERVER_AUTHORIZE_URL');
                    if (!is_string($authorizeUrl) || '' === trim($authorizeUrl)) {
                        return 'common';
                    }

                    $path = (string) parse_url($authorizeUrl, PHP_URL_PATH);
                    if ('' === $path) {
                        return 'common';
                    }

                    $segments = array_values(array_filter(explode('/', trim($path, '/'))));
                    return (string) ($segments[0] ?? 'common');
                })()
            )
        ),
        'proxy' => env('AZURE_PROXY', env('PROXY')),
        'graph_url' => env('AZURE_GRAPH_URL', 'https://graph.microsoft.com/v1.0/me'),
        'logout' => env('AZURE_LOGOUT_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', env('OAUTH_CLIENTID')),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', env('OAUTH_SECRET')),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('OAUTH_REDIRECT_URI')),
        'hosted_domain' => env('GOOGLE_HOSTED_DOMAIN', env('OAUTH_HOSTED_DOMAIN')),
        'scope' => env('GOOGLE_SCOPE', env('OAUTH_SCOPE')),
    ],

];
