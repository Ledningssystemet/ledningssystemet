<?php

return [
    'errors' => [
        'unauthenticated' => 'Ej autentiserad.',
        'session_auth_required' => 'Denna endpoint kraver sessionsautentisering.',
    ],

    'documentation' => [
        'resource_section_title' => 'Upptackbara generiska CRUD-resurser',
        'resource_section_hint' => 'Fullstandig resurskatalog: GET /api/crud/resources',
        'resource_line' => '- `:resource` -> modell `:model` (tabell `:table`)',
        'generic_crud_tag_description' => 'Generiska CRUD-endpoints. Anropa GET /api/crud/resources for att se tillgangliga resurser i den har miljoen.',
        'page_title' => 'Dokumentation for oppet API',
        'page_description' => 'Interaktiv Swagger UI for API-endpoints med Bearer-tokenautentisering.',
        'spec_link_label' => 'Oppna OpenAPI JSON',
        'server_description' => 'Primar API-server',
        'auth_description' => 'Anvand en personlig API-token i Authorization-headern som Bearer <token>.',

        'info' => [
            'title' => 'Ledningssystemet Oppet API',
            'description' => 'OpenAPI-specifikation for API-endpoints som ar tillgangliga med Sanctum Bearer-tokenautentisering.',
        ],

        'tags' => [
            'navigation' => 'Navigering',
            'tokens' => 'Personliga token',
            'admin_tokens' => 'Admin-token',
            'generic_crud' => 'Generisk CRUD',
            'processes' => 'Processer',
            'access_groups' => 'Behorighetsgrupper',
        ],

        'responses' => [
            'success' => 'Lyckat svar',
            'created' => 'Resurs skapad',
            'no_content' => 'Inget innehall',
            'bad_request' => 'Felaktig forfragan',
            'unauthenticated' => 'Autentisering kravs',
            'forbidden' => 'Forbjudet',
            'validation_error' => 'Valideringsfel',
        ],

        'parameters' => [
            'default_path' => 'Path-parameter',
            'token_id' => 'Token-id',
            'resource' => 'Resursnamn, till exempel customers',
            'resource_examples' => 'Exempel: :examples. For fullstandig lista, anropa GET /api/crud/resources.',
            'resource_id' => 'Resurs-id',
            'process_id' => 'Process-id',
            'search' => 'Sokstrang',
            'sort' => 'Sorteringsuttryck, anvand -falt for fallande',
            'admin_token_sort' => 'Sortera pa id, name, user_id, last_used_at, expires_at eller created_at. Prefixa med - for fallande.',
            'paginate' => 'Satt true for paginerat svar',
            'per_page' => 'Antal poster per sida',
            'page' => 'Sidnummer',
            'select' => 'Kommaseparerade kolumnnamn',
            'extends' => 'Kommaseparerade relationsutokningar',
        ],

        'schemas' => [
            'admin_api_token_index' => 'Kan returnera antingen en vanlig array eller ett Laravel-paginatorobjekt beroende pa queryparametern paginate.',
            'generic_crud_mutation_request' => 'Payload beror pa vald resurs och modellens valideringsregler.',
            'generic_crud_index' => 'Kan returnera antingen en vanlig array eller ett Laravel-paginatorobjekt beroende pa queryparametern paginate.',
        ],

        'routes' => [
            'fallback' => [
                'description' => 'Endpoint med Bearer-tokenautentisering.',
            ],
            'crud_resource_suffix' => 'Denna operation ar dokumenterad for resursen ":resource".',
            'menu_badges' => [
                'summary' => 'Hamta menybadgar',
                'description' => 'Returnerar badge-antal for menyobjekt och kategorier for autentiserad anvandare.',
            ],
            'tokens_index' => [
                'summary' => 'Lista personliga access-token',
                'description' => 'Returnerar token for den aktuellt autentiserade anvandaren.',
            ],
            'tokens_destroy_current' => [
                'summary' => 'Aterkalla nuvarande token',
                'description' => 'Aterkallar Bearer-token som anvands i den aktuella forfragan.',
            ],
            'tokens_destroy' => [
                'summary' => 'Aterkalla anvandarens token',
                'description' => 'Aterkallar en av den autentiserade anvandarens token via id.',
            ],
            'admin_tokens_index' => [
                'summary' => 'Lista adminhanterade API-token',
                'description' => 'Returnerar API-token som hanteras via adminendpointen for API-token.',
            ],
            'admin_tokens_update' => [
                'summary' => 'Uppdatera adminhanterad API-token',
                'description' => 'Uppdaterar tokennamn for en adminhanterad personlig access-token.',
            ],
            'admin_tokens_destroy' => [
                'summary' => 'Ta bort adminhanterad API-token',
                'description' => 'Tar bort en adminhanterad personlig access-token.',
            ],
            'crud_index' => [
                'summary' => 'Lista poster i generisk CRUD-resurs',
                'description' => 'Returnerar poster for en konfigurerad generisk CRUD-resurs.',
            ],
            'crud_resources' => [
                'summary' => 'Lista upptackbara generiska CRUD-resurser',
                'description' => 'Returnerar resursnamn och modellklasser som kan anropas via /api/crud/{resource}.',
            ],
            'crud_store' => [
                'summary' => 'Skapa post i generisk CRUD-resurs',
                'description' => 'Skapar en ny post for en konfigurerad generisk CRUD-resurs.',
            ],
            'crud_show' => [
                'summary' => 'Visa post i generisk CRUD-resurs',
                'description' => 'Returnerar en post fran en konfigurerad generisk CRUD-resurs.',
            ],
            'crud_update' => [
                'summary' => 'Uppdatera post i generisk CRUD-resurs',
                'description' => 'Uppdaterar en post i en konfigurerad generisk CRUD-resurs.',
            ],
            'crud_destroy' => [
                'summary' => 'Ta bort post i generisk CRUD-resurs',
                'description' => 'Tar bort en post i en konfigurerad generisk CRUD-resurs.',
            ],
            'processes_publish' => [
                'summary' => 'Publicera BPMN for process',
                'description' => 'Validerar och publicerar BPMN-data for en process.',
            ],
            'access_group_claims' => [
                'summary' => 'Lista behorighetsgruppers claims',
                'description' => 'Returnerar alla tillgangliga claims for behorighetsgrupper for val i UI.',
            ],
        ],
    ],
];
