<?php

return [
    // Supported values: hybrid, password, oauth
    'login_mode' => env('AUTH_LOGIN_MODE', 'hybrid'),

    'oauth' => [
        'enabled' => env('AUTH_OAUTH_ENABLED', false),
        // Supported values: google, microsoft
        'provider' => env('AUTH_OAUTH_PROVIDER', ''),
        'client_id' => env('AUTH_OAUTH_CLIENT_ID'),
        'client_secret' => env('AUTH_OAUTH_CLIENT_SECRET'),
        'redirect' => env('AUTH_OAUTH_REDIRECT_URI'),
        'tenant_id' => env('AUTH_OAUTH_TENANT_ID', 'common'),
        'workspace_domain' => env('AUTH_OAUTH_WORKSPACE_DOMAIN'),
        'external_group_sync_enabled' => env('AUTH_EXTERNAL_GROUP_SYNC_ENABLED', false),
        'external_provider_name' => env('AUTH_EXTERNAL_PROVIDER_NAME', 'External provider'),
    ],

    'mfa' => [
        'enabled' => env('AUTH_MFA_ENABLED', false),
        'enforce' => env('AUTH_MFA_ENFORCE', false),
        'otp_ttl' => env('AUTH_MFA_OTP_TTL', 10),
    ],
];
