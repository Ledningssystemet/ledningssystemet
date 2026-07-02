<?php

return [
    'errors' => [
        'unauthenticated' => 'Ej autentiserad.',
        'session_auth_required' => 'Denna slutpunkt kräver sessionsautentisering.',
    ],

    'tokens' => [
        'expires_at_future' => 'expires_at måste ligga i framtiden.',
        'no_current_access_token' => 'Ingen aktuell access token är kopplad till denna begäran.',
    ],

    'item_status' => [
        'invalid_type' => 'Ogiltig typ',
        'id_and_department_conflict' => 'Kan inte ange både id och department_id',
    ],

    'document_versions' => [
        'already_finished' => 'Dokumentversionen är redan avslutad.',
        'not_authorized_approve' => 'Du har inte behörighet att godkänna denna version.',
        'already_approved' => 'Dokumentversionen är redan godkänd.',
        'must_finish_before_approval' => 'Dokumentversionen måste avslutas innan godkännande.',
        'not_authorized_reject' => 'Du har inte behörighet att avvisa denna version.',
        'cannot_reject_approved' => 'Kan inte avvisa en redan godkänd version.',
    ],

    'assessment_settings' => [
        'risk_mappings_saved' => 'Riskmappningar sparades.',
        'require_levels_before_save' => 'Sannolikhetsnivåer, konsekvensnivåer och risknivåer måste finnas innan mappningar kan sparas.',
        'duplicate_mapping_for_pair' => 'Duplicerad mappning för paret :pair.',
        'unknown_pairs' => 'Mappningarna innehåller okända sannolikhets-/konsekvenspar.',
        'all_combinations_required' => 'Alla kombinationer av sannolikhet och konsekvens måste mappas innan sparning.',
    ],

    'generic_crud' => [
        'unknown_resource' => 'Okänd resurs.',
        'filter_field_not_allowed' => 'Filterfält [:field] är inte tillåtet.',
        'extend_not_allowed' => 'Extend [:extend] är inte tillåtet.',
        'filter_value_boolean' => 'Filtervärde för [:field] måste vara booleskt.',
        'filter_value_numeric' => 'Filtervärde för [:field] måste vara numeriskt.',
    ],

    'reassign' => [
        'ok' => 'ok',
    ],

    'documentation' => [
        'resource_section_title' => 'Upptäckbara Generic CRUD-resurser',
        'resource_section_hint' => 'Fullständig resurssöksväg: GET /api/crud/resources',
        'resource_line' => '- `:resource` -> modell `:model` (tabell `:table`)',
        'generic_crud_tag_description' => 'Generic CRUD-endpoints. Anropa GET /api/crud/resources för att upptäcka tillgängliga resurser i denna miljö.',
        'page_title' => 'Öppna API-dokumentation',
        'page_description' => 'Interaktiv Swagger UI för API-slutpunkter med bearer-tokenautentisering.',
        'spec_link_label' => 'Öppna råa OpenAPI JSON',
        'server_description' => 'Primär API-server',
        'auth_description' => 'Använd en personlig access token i Authorization-headern som Bearer <token>.',

        'info' => [
            'title' => 'Ledningssystemet Open API',
            'description' => 'OpenAPI-specifikation för API-slutpunkter tillgängliga med Sanctum bärartoken-autentisering.',
        ],

        'tags' => [
            'navigation' => 'Navigering',
            'tokens' => 'Personliga tokens',
            'admin_tokens' => 'Admin-tokens',
            'generic_crud' => 'Generic CRUD',
            'processes' => 'Processer',
            'access_groups' => 'Åtkomstgrupper',
        ],

        'responses' => [
            'success' => 'Lyckat svar',
            'created' => 'Resurs skapad',
            'no_content' => 'Inget innehåll',
            'bad_request' => 'Felaktig begäran',
            'unauthenticated' => 'Autentisering krävs',
            'forbidden' => 'Förbjudet',
            'validation_error' => 'Valideringsfel',
        ],

        'parameters' => [
            'default_path' => 'Sökvägsparameter',
            'token_id' => 'Tokenidentifierare',
            'resource' => 'Resursnamn, till exempel customers',
            'resource_examples' => 'Exempel: :examples. För fullständig lista, anropa GET /api/crud/resources.',
            'resource_id' => 'Resursidentifierare',
            'process_id' => 'Processidentifierare',
            'search' => 'Sökfras',
            'sort' => 'Sorteringsuttryck, använd -field för fallande',
            'admin_token_sort' => 'Sortera på id, namn, user_id, last_used_at, expires_at eller created_at. Prefixa med - för fallande.',
            'paginate' => 'Ange true för paginerade resultat',
            'per_page' => 'Antal per sida',
            'page' => 'Sidnummer',
            'select' => 'Kommaseparerade kolumnnamn',
            'extends' => 'Kommaseparerade relationsextensions',
        ],

        'schemas' => [
            'admin_api_token_index' => 'Kan returnera antingen en vanlig array eller ett Laravel paginator-objekt beroende på queryparametern paginate.',
            'generic_crud_mutation_request' => 'Payload beror på vald resurs och modellens valideringsregler.',
            'generic_crud_index' => 'Kan returnera antingen en vanlig array eller ett Laravel paginator-objekt beroende på queryparametern paginate.',
        ],

        'routes' => [
            'fallback' => [
                'description' => 'Slutpunkt med autentisering av bärartoken.',
            ],
            'crud_resource_suffix' => 'Denna operation är dokumenterad för resursen ":resource".',
            'menu_badges' => [
                'summary' => 'Hämta menybrickor',
                'description' => 'Returnerar brickantal för menyposter och kategorier för den autentiserade användaren.',
            ],
            'tokens_index' => [
                'summary' => 'Lista personliga access tokens',
                'description' => 'Returnerar tokens för den aktuellt autentiserade användaren.',
            ],
            'tokens_destroy_current' => [
                'summary' => 'Återkalla aktuell token',
                'description' => 'Återkallar bearer-tokenen som används i den aktuella begäran.',
            ],
            'tokens_destroy' => [
                'summary' => 'Återkalla användartoken',
                'description' => 'Återkallar en av den autentiserade användarens tokens via id.',
            ],
            'admin_tokens_index' => [
                'summary' => 'Lista adminhanterade API-tokens',
                'description' => 'Returnerar API-tokens som hanteras via adminendpointen för API-tokens.',
            ],
            'admin_tokens_update' => [
                'summary' => 'Uppdatera adminhanterad API-token',
                'description' => 'Uppdaterar tokennamn för en adminhanterad personlig access token.',
            ],
            'admin_tokens_destroy' => [
                'summary' => 'Ta bort adminhanterad API-token',
                'description' => 'Tar bort en adminhanterad personlig access token.',
            ],
            'crud_index' => [
                'summary' => 'Lista poster i Generic CRUD-resurs',
                'description' => 'Returnerar poster för en konfigurerad Generic CRUD-resurs.',
            ],
            'crud_resources' => [
                'summary' => 'Lista upptäckbara Generic CRUD-resurser',
                'description' => 'Returnerar resursnamn och modellklasser som kan anropas via /api/crud/{resource}.',
            ],
            'crud_store' => [
                'summary' => 'Skapa post i Generic CRUD-resurs',
                'description' => 'Skapar en ny post för en konfigurerad Generic CRUD-resurs.',
            ],
            'crud_show' => [
                'summary' => 'Visa post i Generic CRUD-resurs',
                'description' => 'Returnerar en enskild post från en konfigurerad Generic CRUD-resurs.',
            ],
            'crud_update' => [
                'summary' => 'Uppdatera post i Generic CRUD-resurs',
                'description' => 'Uppdaterar en post i en konfigurerad Generic CRUD-resurs.',
            ],
            'crud_destroy' => [
                'summary' => 'Ta bort post i Generic CRUD-resurs',
                'description' => 'Tar bort en post i en konfigurerad Generic CRUD-resurs.',
            ],
            'processes_publish' => [
                'summary' => 'Publicera process-BPMN',
                'description' => 'Validerar och publicerar BPMN-data för en process.',
            ],
            'access_group_claims' => [
                'summary' => 'Lista claims för åtkomstgrupper',
                'description' => 'Returnerar alla tillgängliga claims för åtkomstgrupper för val i gränssnittet.',
            ],
        ],
    ],
];
