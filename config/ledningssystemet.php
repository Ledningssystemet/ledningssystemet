<?php

return [
   // Application name
   'application_name' => env('APP_NAME', 'Ledningssystemet.se'),
   'application_url' => env('APP_URL', 'https://ledningssystemet.se'),

   // Microsoft Exchange / mail integration configuration
   'exchangeproxy_url' => env('EXCHANGEPROXY_URL'),
   'exchangeproxy_token' => env('EXCHANGEPROXY_TOKEN'),

   // Company branding and local account settings
   'company_name' => env('COMPANY_NAME', ''),
   'company_uid' => env('COMPANY_UID', ''),

   'local_user_management' => env('LOCAL_USER_MANAGEMENT', true),
   // When disabled with exactly one enabled SSO provider, login redirects directly to SSO.
   'login_allow_passwordlogin' => env('LOGIN_ALLOW_PASSWORDLOGIN', true),
   'mfa_enabled' => env('MFA_ENABLED', false),
   'mfa_enforced' => env('MFA_ENFORCED', false),
   'trusted_device_days' => env('TRUSTED_DEVICE_DAYS', 30),

   // Single sign-on settings
   'sso_azure_button_text' => env('SSO_AZURE_BUTTON_TEXT', 'Microsoft Entra ID'),
   'sso_google_button_text' => env('SSO_GOOGLE_BUTTON_TEXT', 'Google Workspace'),

   // Microsoft Graph synchronization settings
   'graph_provider_name' => env('GRAPH_PROVIDER_NAME', 'External provider'),
   'graph_sync_enabled' => env('GRAPH_SYNC_ENABLED', false),
   'graph_sync_external_users' => env('GRAPH_SYNC_EXTERNAL_USERS', false),
   // Optional full Graph API URL; when set, this takes precedence over GRAPH_USERSYNC_FILTER.
   'graph_usersync_path' => env('GRAPH_USERSYNC_PATH', ''),
   'graph_usersync_filter' => env('GRAPH_USERSYNC_FILTER', ''),
   'graph_management_sync' => env('GRAPH_MANAGEMENT_SYNC', false),
   // Optional full Graph API URL; when set, this takes precedence over GRAPH_GROUPSYNC_FILTER.
   'graph_groupsync_path' => env('GRAPH_GROUPSYNC_PATH', ''),
   'graph_groupsync_filter' => env('GRAPH_GROUPSYNC_FILTER', ''),

];
