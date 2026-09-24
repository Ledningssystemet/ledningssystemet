<?php

return [
   // Application name
   'application_name' => env('APP_NAME', 'Ledningssystemet.se'),
   'application_url' => env('APP_URL', 'https://ledningssystemet.se'),

   // Contact details and privacy contact information
   'contact_name' => env('CONTACT_NAME', 'Admin'),
   'contact_email' => env('CONTACT_EMAIL', 'support@ledningssystemet.se'),
   'data_privacy_contact_name' => env('COMPANY_DATA_PRIVACY_CONTACT_NAME', ''),
   'data_privacy_contact_email' => env('COMPANY_DATA_PRIVACY_CONTACT_EMAIL', ''),

   // Feature toggles for demo and optional modules
   'disable_staff' => env('DISABLE_STAFF', false),
   'disable_processes' => env('DISABLE_PROCESSES', false),
   'disable_finding' => env('DISABLE_FINDING', false),
   'disable_supplier' => env('DISABLE_SUPPLIER', false),
   'disable_gdpr' => env('DISABLE_GDPR', false),
   'disable_archival' => env('DISABLE_ARCHIVAL', true),

   // Microsoft Exchange / mail integration configuration
   'exchangeproxy_url' => env('EXCHANGEPROXY_URL'),
   'exchangeproxy_token' => env('EXCHANGEPROXY_TOKEN'),

   // Company branding and local account settings
   'company_name' => env('COMPANY_NAME', ''),
   'company_uid' => env('COMPANY_UID', ''),

   'local_user_management' => env('LOCAL_USER_MANAGEMENT', true),
   'login_allow_passwordlogin' => env('LOGIN_ALLOW_PASSWORDLOGIN', true),
   'mfa_enabled' => env('MFA_ENABLED', false),
   'mfa_enforced' => env('MFA_ENFORCED', false),
   'trusted_device_days' => env('TRUSTED_DEVICE_DAYS', 30),

   // Single sign-on (SSO) and OAuth configuration
   'oauth_button_text' => env('OAUTH_BUTTON_TEXT', 'Microsoft Entra ID'),
   'oauth_server_authorize_url' => env('OAUTH_SERVER_AUTHORIZE_URL'),
   'oauth_server_token_url' => env('OAUTH_SERVER_TOKEN_URL'),
   'oauth_client_id' => env('OAUTH_CLIENTID'),
   'oauth_client_secret' => env('OAUTH_SECRET'),
   'oauth_scope' => env('OAUTH_SCOPE', 'openid,email,profile,offline_access,User.Read'),
   'oauth_logout_url' => env('OAUTH_LOGOUT_URL'),
   'sso_azure_button_text' => env('SSO_AZURE_BUTTON_TEXT', env('OAUTH_BUTTON_TEXT', 'Microsoft Entra ID')),
   'sso_google_button_text' => env('SSO_GOOGLE_BUTTON_TEXT', 'Google Workspace'),

   // Microsoft Graph synchronization settings
   'graph_provider_name' => env('GRAPH_PROVIDER_NAME', 'External provider'),
   'graph_usersync_path' => env('GRAPH_USERSYNC_PATH', ''),
   'graph_groupsync_path' => env('GRAPH_GROUPSYNC_PATH', ''),
   'graph_groupusers_path' => env('GRAPH_GROUPUSERS_PATH', ''),
   'graph_management_path' => env('GRAPH_MANAGEMENT_PATH'),
   'graph_usersync_namefield' => env('GRAPH_USERSYNC_NAMEFIELD', 'displayName'),
   'graph_usersync_titlefield' => env('GRAPH_USERSYNC_TITLEFIELD', 'jobTitle'),
   'graph_usersync_enabledfield' => env('GRAPH_USERSYNC_ENABLEDFIELD', 'accountEnabled'),
   'graph_usersync_emailfield' => env('GRAPH_USERSYNC_EMAILFIELD', 'userPrincipalName'),
   'graph_departments_assignusers' => env('GRAPH_DEPARTMENTS_ASSIGNUSERS', false),
   'graph_departments_name_delimiter' => env('GRAPH_DEPARTMENTS_NAME_DELIMITER', '-'),
   'graph_ignore_departments' => env('GRAPH_IGNORE_DEPARTMENTS', ''),
   'graph_departments_pattern_level1' => env('GRAPH_DEPARTMENTS_PATTERN_LEVEL1', ''),
   'graph_departments_pattern_level2' => env('GRAPH_DEPARTMENTS_PATTERN_LEVEL2', ''),
   'graph_departments_pattern_level3' => env('GRAPH_DEPARTMENTS_PATTERN_LEVEL3', ''),
   'graph_departments_pattern_level4' => env('GRAPH_DEPARTMENTS_PATTERN_LEVEL4', ''),
   'graph_departments_pattern_level5' => env('GRAPH_DEPARTMENTS_PATTERN_LEVEL5', ''),

   // OpenAI / AI assistant settings
   'openai_endpoint' => env('OPENAI_ENDPOINT'),
   'openai_api_key' => env('OPENAI_API_KEY'),
   'openai_model' => env('OPENAI_MODEL'),
   'openai_frequency_penalty' => env('OPENAI_FREQUENCY_PENALTY'),
   'openai_presence_penalty' => env('OPENAI_PRESENCE_PENALTY'),
   'openai_temperature' => env('OPENAI_TEMPERATURE'),
   'openai_top_p' => env('OPENAI_TOP_P'),
   'openai_max_completion_tokens' => env('OPENAI_MAX_COMPLETION_TOKENS'),
   'ai_chat_enabled' => env('AI_CHAT_ENABLED', false),
   'ai_chat_name' => env('AI_CHAT_NAME'),
];
