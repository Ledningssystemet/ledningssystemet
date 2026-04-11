<?php

namespace App\Support\OpenApi;

use App\Support\Crud\CrudResourceCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

class OpenApiSpecBuilder
{
    public function __construct(
        private readonly CrudResourceCatalog $crudResourceCatalog,
    ) {}

    public function build(): array
    {
        $paths = [];
        $crudResourceLines = $this->crudResourceDescriptionLines();

        foreach (Route::getRoutes() as $route) {
            if (! $this->shouldDocument($route)) {
                continue;
            }

            $path = '/'.$route->uri();

            foreach ($this->documentedMethods($route) as $method) {
                $paths[$path][Str::lower($method)] = $this->operationFor($route, Str::lower($method));
            }
        }

        $this->expandConcreteCrudPaths($paths);

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => __('api.documentation.info.title'),
                'description' => $this->infoDescriptionWithResourceSection($crudResourceLines),
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => rtrim(url('/'), '/'),
                    'description' => __('api.documentation.server_description'),
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'tags' => $this->tags($crudResourceLines),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'API token',
                        'description' => __('api.documentation.auth_description'),
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
        ];
    }

    private function shouldDocument(IlluminateRoute $route): bool
    {
        if (! str_starts_with($route->uri(), 'api/')) {
            return false;
        }

        $middleware = $route->gatherMiddleware();

        return in_array('auth:sanctum', $middleware, true)
            && ! in_array('session.authenticated', $middleware, true);
    }

    /**
     * @return array<int, string>
     */
    private function documentedMethods(IlluminateRoute $route): array
    {
        return array_values(array_filter(
            $route->methods(),
            static fn (string $method): bool => ! in_array($method, ['HEAD'], true)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function operationFor(IlluminateRoute $route, string $method): array
    {
        $metadata = $this->metadataFor($route, $method);

        return array_filter([
            'tags' => $metadata['tags'] ?? [$this->fallbackTag($route)],
            'summary' => $metadata['summary'] ?? $this->fallbackSummary($route, $method),
            'description' => $metadata['description'] ?? __('api.documentation.routes.fallback.description'),
            'operationId' => $this->operationId($route, $method),
            'parameters' => $this->mergeParameters(
                $this->pathParameters($route, $metadata['path_parameters'] ?? []),
                $metadata['query_parameters'] ?? []
            ),
            'requestBody' => $metadata['request_body'] ?? null,
            'responses' => $metadata['responses'] ?? $this->defaultResponses(),
            'security' => [
                ['bearerAuth' => []],
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFor(IlluminateRoute $route, string $method): array
    {
        $name = $route->getName();

        return match ($name) {
            'api.menu.badges' => [
                'tags' => [$this->tagName('navigation')],
                'summary' => __('api.documentation.routes.menu_badges.summary'),
                'description' => __('api.documentation.routes.menu_badges.description'),
                'responses' => $this->jsonResponse('MenuBadgeResponse'),
            ],
            'api.tokens.index' => [
                'tags' => [$this->tagName('tokens')],
                'summary' => __('api.documentation.routes.tokens_index.summary'),
                'description' => __('api.documentation.routes.tokens_index.description'),
                'responses' => $this->jsonResponse('PersonalAccessTokenIndexResponse'),
            ],
            'api.tokens.destroy-current' => [
                'tags' => [$this->tagName('tokens')],
                'summary' => __('api.documentation.routes.tokens_destroy_current.summary'),
                'description' => __('api.documentation.routes.tokens_destroy_current.description'),
                'responses' => $this->noContentResponses(includeBadRequest: true),
            ],
            'api.tokens.destroy' => [
                'tags' => [$this->tagName('tokens')],
                'summary' => __('api.documentation.routes.tokens_destroy.summary'),
                'description' => __('api.documentation.routes.tokens_destroy.description'),
                'path_parameters' => [
                    'tokenId' => $this->integerPathParameter('tokenId', __('api.documentation.parameters.token_id')),
                ],
                'responses' => $this->noContentResponses(),
            ],
            'api.admin.api-tokens.index' => [
                'tags' => [$this->tagName('admin_tokens')],
                'summary' => __('api.documentation.routes.admin_tokens_index.summary'),
                'description' => __('api.documentation.routes.admin_tokens_index.description'),
                'query_parameters' => [
                    $this->stringQueryParameter('search', __('api.documentation.parameters.search')),
                    $this->stringQueryParameter('sort', __('api.documentation.parameters.admin_token_sort'), '-created_at'),
                    $this->booleanQueryParameter('paginate', __('api.documentation.parameters.paginate'), true),
                    $this->integerQueryParameter('per_page', __('api.documentation.parameters.per_page'), 25),
                ],
                'responses' => $this->jsonResponse('AdminApiTokenIndexResponse', includeForbidden: true),
            ],
            'api.admin.api-tokens.update' => [
                'tags' => [$this->tagName('admin_tokens')],
                'summary' => __('api.documentation.routes.admin_tokens_update.summary'),
                'description' => __('api.documentation.routes.admin_tokens_update.description'),
                'path_parameters' => [
                    'tokenId' => $this->integerPathParameter('tokenId', __('api.documentation.parameters.token_id')),
                ],
                'request_body' => $this->requestBody('AdminApiTokenUpdateRequest'),
                'responses' => $this->jsonResponse('AdminApiToken', includeForbidden: true, includeValidation: true),
            ],
            'api.admin.api-tokens.destroy' => [
                'tags' => [$this->tagName('admin_tokens')],
                'summary' => __('api.documentation.routes.admin_tokens_destroy.summary'),
                'description' => __('api.documentation.routes.admin_tokens_destroy.description'),
                'path_parameters' => [
                    'tokenId' => $this->integerPathParameter('tokenId', __('api.documentation.parameters.token_id')),
                ],
                'responses' => $this->noContentResponses(includeForbidden: true),
            ],
            'api.crud.resources' => [
                'tags' => [$this->tagName('generic_crud')],
                'summary' => __('api.documentation.routes.crud_resources.summary'),
                'description' => __('api.documentation.routes.crud_resources.description'),
                'responses' => $this->jsonResponse('CrudResourceCatalogResponse'),
            ],
            'api.crud.index' => [
                'tags' => [$this->tagName('generic_crud')],
                'summary' => __('api.documentation.routes.crud_index.summary'),
                'description' => __('api.documentation.routes.crud_index.description'),
                'path_parameters' => [
                    'resource' => $this->resourcePathParameter(),
                ],
                'query_parameters' => [
                    $this->booleanQueryParameter('paginate', __('api.documentation.parameters.paginate'), true),
                    $this->integerQueryParameter('page', __('api.documentation.parameters.page'), 1),
                    $this->integerQueryParameter('per_page', __('api.documentation.parameters.per_page'), 25),
                    $this->stringQueryParameter('search', __('api.documentation.parameters.search')),
                    $this->stringQueryParameter('sort', __('api.documentation.parameters.sort')),
                    $this->stringQueryParameter('$select', __('api.documentation.parameters.select')),
                    $this->stringQueryParameter('extends', __('api.documentation.parameters.extends')),
                ],
                'responses' => $this->jsonResponse('GenericCrudIndexResponse', includeForbidden: true),
            ],
            'api.crud.store' => [
                'tags' => [$this->tagName('generic_crud')],
                'summary' => __('api.documentation.routes.crud_store.summary'),
                'description' => __('api.documentation.routes.crud_store.description'),
                'path_parameters' => [
                    'resource' => $this->resourcePathParameter(),
                ],
                'request_body' => $this->requestBody('GenericCrudMutationRequest'),
                'responses' => $this->createdJsonResponse('GenericCrudResource', includeForbidden: true, includeValidation: true),
            ],
            'api.crud.show' => [
                'tags' => [$this->tagName('generic_crud')],
                'summary' => __('api.documentation.routes.crud_show.summary'),
                'description' => __('api.documentation.routes.crud_show.description'),
                'path_parameters' => [
                    'resource' => $this->resourcePathParameter(),
                    'id' => $this->stringPathParameter('id', __('api.documentation.parameters.resource_id'), '1'),
                ],
                'query_parameters' => [
                    $this->stringQueryParameter('$select', __('api.documentation.parameters.select')),
                    $this->stringQueryParameter('extends', __('api.documentation.parameters.extends')),
                ],
                'responses' => $this->jsonResponse('GenericCrudResource', includeForbidden: true),
            ],
            'api.crud.update' => [
                'tags' => [$this->tagName('generic_crud')],
                'summary' => __('api.documentation.routes.crud_update.summary'),
                'description' => __('api.documentation.routes.crud_update.description'),
                'path_parameters' => [
                    'resource' => $this->resourcePathParameter(),
                    'id' => $this->stringPathParameter('id', __('api.documentation.parameters.resource_id'), '1'),
                ],
                'request_body' => $this->requestBody('GenericCrudMutationRequest'),
                'responses' => $this->jsonResponse('GenericCrudResource', includeForbidden: true, includeValidation: true),
            ],
            'api.crud.destroy' => [
                'tags' => [$this->tagName('generic_crud')],
                'summary' => __('api.documentation.routes.crud_destroy.summary'),
                'description' => __('api.documentation.routes.crud_destroy.description'),
                'path_parameters' => [
                    'resource' => $this->resourcePathParameter(),
                    'id' => $this->stringPathParameter('id', __('api.documentation.parameters.resource_id'), '1'),
                ],
                'responses' => $this->noContentResponses(includeForbidden: true),
            ],
            'api.processes.publish' => [
                'tags' => [$this->tagName('processes')],
                'summary' => __('api.documentation.routes.processes_publish.summary'),
                'description' => __('api.documentation.routes.processes_publish.description'),
                'path_parameters' => [
                    'process' => $this->integerPathParameter('process', __('api.documentation.parameters.process_id')),
                ],
                'request_body' => $this->requestBody('ProcessPublishRequest'),
                'responses' => $this->jsonResponse('GenericCrudResource', includeForbidden: true, includeValidation: true),
            ],
            'api.access-groups.claims' => [
                'tags' => [$this->tagName('access_groups')],
                'summary' => __('api.documentation.routes.access_group_claims.summary'),
                'description' => __('api.documentation.routes.access_group_claims.description'),
                'responses' => $this->jsonResponse('AccessGroupClaimOptionsResponse', includeForbidden: true),
            ],
            default => [],
        };
    }

    /**
     * @param array<string, array<string, mixed>> $overrides
     * @return array<int, array<string, mixed>>
     */
    private function pathParameters(IlluminateRoute $route, array $overrides = []): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[] = $overrides[$name] ?? $this->stringPathParameter($name, __('api.documentation.parameters.default_path'));
        }

        return $parameters;
    }

    /**
     * @param array<int, array<string, mixed>> $pathParameters
     * @param array<int, array<string, mixed>> $queryParameters
     * @return array<int, array<string, mixed>>
     */
    private function mergeParameters(array $pathParameters, array $queryParameters): array
    {
        return array_values([...$pathParameters, ...$queryParameters]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tags(array $crudResourceLines = []): array
    {
        return [
            ['name' => $this->tagName('navigation')],
            ['name' => $this->tagName('tokens')],
            ['name' => $this->tagName('admin_tokens')],
            [
                'name' => $this->tagName('generic_crud'),
                'description' => $this->genericCrudTagDescription($crudResourceLines),
            ],
            ['name' => $this->tagName('processes')],
            ['name' => $this->tagName('access_groups')],
        ];
    }

    /**
     * @param array<int, string> $crudResourceLines
     */
    private function infoDescriptionWithResourceSection(array $crudResourceLines): string
    {
        $description = (string) __('api.documentation.info.description');

        if ($crudResourceLines === []) {
            return $description;
        }

        $sectionHeader = (string) __('api.documentation.resource_section_title');
        $sectionHint = (string) __('api.documentation.resource_section_hint');

        return trim($description."\n\n### {$sectionHeader}\n{$sectionHint}\n\n".implode("\n", $crudResourceLines));
    }

    /**
     * @param array<int, string> $crudResourceLines
     */
    private function genericCrudTagDescription(array $crudResourceLines): string
    {
        $description = (string) __('api.documentation.generic_crud_tag_description');

        if ($crudResourceLines === []) {
            return $description;
        }

        return trim($description."\n\n".implode("\n", $crudResourceLines));
    }

    /**
     * @return array<int, string>
     */
    private function crudResourceDescriptionLines(): array
    {
        $lines = [];

        foreach ($this->crudResourceCatalog->all() as $item) {
            $metadata = $this->resourceMetadata($item['model']);

            $lines[] = (string) __('api.documentation.resource_line', [
                'resource' => $item['resource'],
                'model' => $metadata['model'],
                'table' => $metadata['table'],
            ]);
        }

        return $lines;
    }

    /**
     * @param class-string<Model> $modelClass
     * @return array{model: string, table: string}
     */
    private function resourceMetadata(string $modelClass): array
    {
        $model = class_basename($modelClass);
        $table = Str::snake(Str::plural($model));

        try {
            /** @var Model $instance */
            $instance = new $modelClass();
            $table = (string) $instance->getTable();
        } catch (Throwable) {
            // Fallback to convention-based table name if model instantiation fails.
        }

        return [
            'model' => $model,
            'table' => $table,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function schemas(): array
    {
        return [
            'ErrorResponse' => [
                'type' => 'object',
                'required' => ['message'],
                'properties' => [
                    'message' => [
                        'type' => 'string',
                    ],
                ],
            ],
            'ValidationErrorResponse' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/ErrorResponse'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'errors' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'MenuBadge' => [
                'type' => 'object',
                'required' => ['count', 'severity'],
                'properties' => [
                    'count' => ['type' => 'string'],
                    'severity' => ['type' => 'string'],
                ],
            ],
            'MenuBadgeResponse' => [
                'type' => 'object',
                'required' => ['items', 'categories'],
                'properties' => [
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => ['$ref' => '#/components/schemas/MenuBadge'],
                    ],
                    'categories' => [
                        'type' => 'object',
                        'additionalProperties' => ['$ref' => '#/components/schemas/MenuBadge'],
                    ],
                ],
            ],
            'PersonalAccessToken' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'abilities' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'last_used_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                ],
            ],
            'PersonalAccessTokenIndexResponse' => [
                'type' => 'object',
                'required' => ['data', 'current_access_token_id'],
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/PersonalAccessToken'],
                    ],
                    'current_access_token_id' => ['type' => ['integer', 'null']],
                ],
            ],
            'AdminApiToken' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'user_id' => ['type' => 'integer'],
                    'last_used_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'updated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'plain_text_token' => ['type' => 'string'],
                ],
            ],
            'AdminApiTokenUpdateRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                ],
            ],
            'AdminApiTokenIndexResponse' => [
                'description' => __('api.documentation.schemas.admin_api_token_index'),
                'oneOf' => [
                    [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/AdminApiToken'],
                    ],
                    [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/AdminApiToken'],
                            ],
                            'current_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'GenericCrudResource' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'GenericCrudMutationRequest' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => __('api.documentation.schemas.generic_crud_mutation_request'),
            ],
            'GenericCrudIndexResponse' => [
                'description' => __('api.documentation.schemas.generic_crud_index'),
                'oneOf' => [
                    [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/GenericCrudResource'],
                    ],
                    [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/GenericCrudResource'],
                            ],
                            'current_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'ProcessPublishRequest' => [
                'type' => 'object',
                'required' => ['bpmn'],
                'properties' => [
                    'bpmn' => ['type' => 'string'],
                ],
            ],
            'AccessGroupClaimOption' => [
                'type' => 'object',
                'required' => ['id', 'name'],
                'properties' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
            ],
            'AccessGroupClaimOptionsResponse' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/AccessGroupClaimOption'],
            ],
            'CrudResourceCatalogItem' => [
                'type' => 'object',
                'required' => ['resource', 'model'],
                'properties' => [
                    'resource' => ['type' => 'string'],
                    'model' => ['type' => 'string'],
                ],
            ],
            'CrudResourceCatalogResponse' => [
                'type' => 'object',
                'required' => ['data'],
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CrudResourceCatalogItem'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function availableCrudResources(): array
    {
        return $this->crudResourceCatalog->resourceNames();
    }

    /**
     * @return array<string, mixed>
     */
    private function resourcePathParameter(): array
    {
        $resources = $this->availableCrudResources();
        $examples = implode(', ', array_slice($resources, 0, 12));
        $description = __('api.documentation.parameters.resource');

        if ($examples !== '') {
            $description .= ' '.__('api.documentation.parameters.resource_examples', ['examples' => $examples]);
        }

        $parameter = [
            'name' => 'resource',
            'in' => 'path',
            'required' => true,
            'description' => $description,
            'schema' => ['type' => 'string'],
        ];

        if ($resources !== []) {
            $parameter['schema']['enum'] = $resources;
            $parameter['example'] = $resources[0];
        } else {
            $parameter['example'] = 'customers';
        }

        return $parameter;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function jsonResponse(string $schema, bool $includeForbidden = false, bool $includeValidation = false): array
    {
        $responses = [
            '200' => [
                'description' => __('api.documentation.responses.success'),
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/'.$schema],
                    ],
                ],
            ],
            '401' => $this->errorResponse(__('api.documentation.responses.unauthenticated')),
        ];

        if ($includeForbidden) {
            $responses['403'] = $this->errorResponse(__('api.documentation.responses.forbidden'));
        }

        if ($includeValidation) {
            $responses['422'] = $this->validationErrorResponse();
        }

        return $responses;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function createdJsonResponse(string $schema, bool $includeForbidden = false, bool $includeValidation = false): array
    {
        $responses = [
            '201' => [
                'description' => __('api.documentation.responses.created'),
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/'.$schema],
                    ],
                ],
            ],
            '401' => $this->errorResponse(__('api.documentation.responses.unauthenticated')),
        ];

        if ($includeForbidden) {
            $responses['403'] = $this->errorResponse(__('api.documentation.responses.forbidden'));
        }

        if ($includeValidation) {
            $responses['422'] = $this->validationErrorResponse();
        }

        return $responses;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function noContentResponses(bool $includeForbidden = false, bool $includeBadRequest = false): array
    {
        $responses = [
            '204' => [
                'description' => __('api.documentation.responses.no_content'),
            ],
            '401' => $this->errorResponse(__('api.documentation.responses.unauthenticated')),
        ];

        if ($includeForbidden) {
            $responses['403'] = $this->errorResponse(__('api.documentation.responses.forbidden'));
        }

        if ($includeBadRequest) {
            $responses['400'] = $this->errorResponse(__('api.documentation.responses.bad_request'));
        }

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(string $description): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validationErrorResponse(): array
    {
        return [
            'description' => __('api.documentation.responses.validation_error'),
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(string $schema): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/'.$schema],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stringPathParameter(string $name, string $description, ?string $example = null): array
    {
        $parameter = [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'description' => $description,
            'schema' => ['type' => 'string'],
        ];

        if ($example !== null) {
            $parameter['example'] = $example;
        }

        return $parameter;
    }

    /**
     * @return array<string, mixed>
     */
    private function integerPathParameter(string $name, string $description): array
    {
        return [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'description' => $description,
            'schema' => ['type' => 'integer'],
            'example' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stringQueryParameter(string $name, string $description, ?string $example = null): array
    {
        $parameter = [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => ['type' => 'string'],
        ];

        if ($example !== null) {
            $parameter['example'] = $example;
        }

        return $parameter;
    }

    /**
     * @return array<string, mixed>
     */
    private function integerQueryParameter(string $name, string $description, int $example): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => ['type' => 'integer'],
            'example' => $example,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function booleanQueryParameter(string $name, string $description, bool $example): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => ['type' => 'boolean'],
            'example' => $example,
        ];
    }

    private function tagName(string $key): string
    {
        return __('api.documentation.tags.'.$key);
    }

    private function fallbackTag(IlluminateRoute $route): string
    {
        $segments = explode('/', $route->uri());

        return Str::headline($segments[1] ?? 'API');
    }

    private function fallbackSummary(IlluminateRoute $route, string $method): string
    {
        return Str::headline($method.' '.($route->getName() ?? $route->uri()));
    }

    private function operationId(IlluminateRoute $route, string $method): string
    {
        $base = $route->getName() ?? $route->uri();

        return Str::of($method.'_'.$base)
            ->replace(['.', '/', '{', '}', '-'], '_')
            ->squish()
            ->lower()
            ->toString();
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $paths
     */
    private function expandConcreteCrudPaths(array &$paths): void
    {
        $collectionTemplate = $paths['/api/crud/{resource}'] ?? null;
        $itemTemplate = $paths['/api/crud/{resource}/{id}'] ?? null;

        if (! is_array($collectionTemplate) && ! is_array($itemTemplate)) {
            return;
        }

        foreach ($this->availableCrudResources() as $resource) {
            // Keep /api/crud/resources reserved for the discovery endpoint.
            if ($resource === 'resources') {
                continue;
            }

            if (is_array($collectionTemplate)) {
                $collectionPath = '/api/crud/'.$resource;

                foreach ($collectionTemplate as $method => $operation) {
                    $paths[$collectionPath][$method] = $this->concreteCrudOperation(
                        $operation,
                        $resource,
                    );
                }
            }

            if (is_array($itemTemplate)) {
                $itemPath = '/api/crud/'.$resource.'/{id}';

                foreach ($itemTemplate as $method => $operation) {
                    $paths[$itemPath][$method] = $this->concreteCrudOperation(
                        $operation,
                        $resource,
                    );
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function concreteCrudOperation(array $operation, string $resource): array
    {
        $cloned = $operation;

        if (isset($cloned['parameters']) && is_array($cloned['parameters'])) {
            $cloned['parameters'] = array_values(array_filter(
                $cloned['parameters'],
                static fn (mixed $parameter): bool =>
                    ! is_array($parameter)
                    || (($parameter['name'] ?? null) !== 'resource')
            ));

            if ($cloned['parameters'] === []) {
                unset($cloned['parameters']);
            }
        }

        $operationId = $cloned['operationId'] ?? null;
        if (is_string($operationId) && $operationId !== '') {
            $cloned['operationId'] = $operationId.'_'.Str::snake($resource);
        }

        $description = $cloned['description'] ?? null;
        if (is_string($description) && $description !== '') {
            $cloned['description'] = trim($description.' '.__('api.documentation.routes.crud_resource_suffix', [
                'resource' => $resource,
            ]));
        }

        return $cloned;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultResponses(): array
    {
        return [
            '200' => [
                'description' => __('api.documentation.responses.success'),
            ],
            '401' => $this->errorResponse(__('api.documentation.responses.unauthenticated')),
        ];
    }
}

