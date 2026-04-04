<?php

return [
   'company_data_privacy_contact_email' => env('COMPANY_DATA_PRIVACY_CONTACT_EMAIL', ''),
   'company_data_privacy_contact_name' => env('COMPANY_DATA_PRIVACY_CONTACT_NAME', ''),
   'company_name' => env('COMPANY_NAME', 'The Company'),
   'disable_archival' => env('DISABLE_ARCHIVAL', true),
   'disable_finding' => env('DISABLE_FINDING', false),
   'disable_gdpr' => env('DISABLE_GDPR', false),
   'disable_staff' => env('DISABLE_STAFF', false),
   'disable_supplier' => env('DISABLE_SUPPLIER',false),
   'local_user_management' => env('LOCAL_USER_MANAGEMENT', true),
   'login_allow_passwordlogin' => env('LOGIN_ALLOW_PASSWORDLOGIN', true),
   'openai_api_key' => env('OPENAI_API_KEY'),
   'openai_endpoint' => env('OPENAI_ENDPOINT'),
   'openai_frequency_penalty' => env('OPENAI_FREQUENCY_PENALTY'),
   'openai_max_completion_tokens' => env('OPENAI_MAX_COMPLETION_TOKENS', 16000),
   'openai_model' => env('OPENAI_MODEL'),
   'openai_presence_penalty' => env('OPENAI_PRESENCE_PENALTY'),
   'openai_temperature' => env('OPENAI_TEMPERATURE'),
   'openai_top_p' => env('OPENAI_TOP_P'),
   'ai_chat_name' => env('AI_CHAT_NAME', 'AI Assistant'),
   'ai_chat_enabled' => env('AI_CHAT_ENABLED', false),
];
