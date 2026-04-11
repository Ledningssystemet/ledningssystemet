<?php

return [
    'errors' => [
        'unauthenticated' => 'Unauthenticated.',
        'session_auth_required' => 'This endpoint requires session authentication.',
    ],

    'documentation' => [
        'resource_section_title' => 'Discoverable Generic CRUD resources',
        'resource_section_hint' => 'Full resource catalog endpoint: GET /api/crud/resources',
        'resource_line' => '- `:resource` -> model `:model` (table `:table`)',
        'generic_crud_tag_description' => 'Generic CRUD endpoints. Call GET /api/crud/resources to discover available resources in this environment.',
        'page_title' => 'Open API documentation',
        'page_description' => 'Interactive Swagger UI for bearer-token authenticated API endpoints.',
        'spec_link_label' => 'Open raw OpenAPI JSON',
        'server_description' => 'Primary API server',
        'auth_description' => 'Use a personal access token in the Authorization header as Bearer <token>.',

        'info' => [
            'title' => 'Ledningssystemet Open API',
            'description' => 'OpenAPI specification for API endpoints available with Sanctum bearer token authentication.',
        ],

        'tags' => [
            'navigation' => 'Navigation',
            'tokens' => 'Personal tokens',
            'admin_tokens' => 'Admin tokens',
            'generic_crud' => 'Generic CRUD',
            'processes' => 'Processes',
            'access_groups' => 'Access groups',
        ],

        'responses' => [
            'success' => 'Successful response',
            'created' => 'Resource created',
            'no_content' => 'No content',
            'bad_request' => 'Bad request',
            'unauthenticated' => 'Authentication required',
            'forbidden' => 'Forbidden',
            'validation_error' => 'Validation error',
        ],

        'parameters' => [
            'default_path' => 'Path parameter',
            'token_id' => 'Token identifier',
            'resource' => 'Resource name, for example customers',
            'resource_examples' => 'Examples: :examples. For full list, call GET /api/crud/resources.',
            'resource_id' => 'Resource identifier',
            'process_id' => 'Process identifier',
            'search' => 'Search query string',
            'sort' => 'Sort expression, use -field for descending',
            'admin_token_sort' => 'Sort by id, name, user_id, last_used_at, expires_at or created_at. Prefix with - for descending.',
            'paginate' => 'Set true to return paginated results',
            'per_page' => 'Items per page',
            'page' => 'Page number',
            'select' => 'Comma-separated column names',
            'extends' => 'Comma-separated relation extensions',
        ],

        'schemas' => [
            'admin_api_token_index' => 'May return either a plain array or Laravel paginator object depending on the paginate query parameter.',
            'generic_crud_mutation_request' => 'Payload depends on selected resource and model validation rules.',
            'generic_crud_index' => 'May return either a plain array or Laravel paginator object depending on the paginate query parameter.',
        ],

        'routes' => [
            'fallback' => [
                'description' => 'Bearer token authenticated endpoint.',
            ],
            'crud_resource_suffix' => 'This operation is documented for resource ":resource".',
            'menu_badges' => [
                'summary' => 'Get menu badges',
                'description' => 'Returns badge counts for menu items and categories for the authenticated user.',
            ],
            'tokens_index' => [
                'summary' => 'List personal access tokens',
                'description' => 'Returns tokens for the currently authenticated user.',
            ],
            'tokens_destroy_current' => [
                'summary' => 'Revoke current token',
                'description' => 'Revokes the bearer token used in the current request.',
            ],
            'tokens_destroy' => [
                'summary' => 'Revoke user token',
                'description' => 'Revokes one of the authenticated user tokens by id.',
            ],
            'admin_tokens_index' => [
                'summary' => 'List admin-managed API tokens',
                'description' => 'Returns API tokens managed through the admin API token endpoint.',
            ],
            'admin_tokens_update' => [
                'summary' => 'Update admin-managed API token',
                'description' => 'Updates token name for an admin-managed personal access token.',
            ],
            'admin_tokens_destroy' => [
                'summary' => 'Delete admin-managed API token',
                'description' => 'Deletes an admin-managed personal access token.',
            ],
            'crud_index' => [
                'summary' => 'List generic CRUD resource records',
                'description' => 'Returns records for a configured generic CRUD resource.',
            ],
            'crud_resources' => [
                'summary' => 'List discoverable generic CRUD resources',
                'description' => 'Returns resource names and model classes that can be requested via /api/crud/{resource}.',
            ],
            'crud_store' => [
                'summary' => 'Create generic CRUD resource record',
                'description' => 'Creates a new record for a configured generic CRUD resource.',
            ],
            'crud_show' => [
                'summary' => 'Show generic CRUD resource record',
                'description' => 'Returns a single record from a configured generic CRUD resource.',
            ],
            'crud_update' => [
                'summary' => 'Update generic CRUD resource record',
                'description' => 'Updates a record in a configured generic CRUD resource.',
            ],
            'crud_destroy' => [
                'summary' => 'Delete generic CRUD resource record',
                'description' => 'Deletes a record in a configured generic CRUD resource.',
            ],
            'processes_publish' => [
                'summary' => 'Publish process BPMN',
                'description' => 'Validates and publishes BPMN data for a process.',
            ],
            'access_group_claims' => [
                'summary' => 'List access group claims',
                'description' => 'Returns all available access group claims for selection in the UI.',
            ],
        ],
    ],
];
