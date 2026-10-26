<?php

namespace Ledningssystemet\Ledningssystemet\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Controller;
use Spatie\QueryBuilder\AllowedFilter;

class SwaggerController extends Controller
{
    private function getIndexParams(Model $model): array
    {
        $params = [
            [
                'name' => 'sort',
                'in' => 'query',
                'required' => false,
                'type' => 'string',
                'description' => 'Comma-separated allowed sort fields. Prefix a field with - for descending order.',
            ],
            [
                'name' => 'include',
                'in' => 'query',
                'required' => false,
                'type' => 'string',
                'description' => 'Comma-separated allowed relationships to include.',
            ],
            [
                'name' => 'fields['.$model->getTable().']',
                'in' => 'query',
                'required' => false,
                'type' => 'string',
                'description' => 'Comma-separated allowed fields to return. fields[_] is also accepted.',
            ],
            [
                'name' => 'per_page',
                'in' => 'query',
                'required' => false,
                'type' => 'integer',
                'description' => 'Enable pagination with this number of items per page. Omit to return all items.',
            ],
            [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'type' => 'integer',
                'description' => 'Page to retrieve when per_page is supplied.',
            ],
        ];

        if (method_exists($model, 'getAllowedFilters')) {
            foreach ($model::getAllowedFilters() as $filter) {
                $name = $filter instanceof AllowedFilter ? $filter->getName() : $filter;
                $params[] = [
                    'name' => 'filter['.$name.']',
                    'in' => 'query',
                    'required' => false,
                    'type' => 'string',
                ];
            }
        }

        return $params;
    }

    public function index(Request $request): JsonResponse
    {
        $retval = [
            'swagger' => '2.0',
            'info' => [
                'title' => 'Ledningssystemet.se Open API',
                'version' => '1.0.0',
            ],
            'host' => $request->getHttpHost(),
            'basePath' => '/',
            'tags' => [
            ],
            'schemes' => [
                (strpos(config('app.url'), 'https') === 0) ? 'https' : 'http',
            ],
            'securityDefinitions' => [
                'Bearer' => [
                    'type' => 'apiKey',
                    'name' => 'Authorization',
                    'in' => 'header',
                ],
            ],
            'paths' => [
            ],
            'security' => [
                ['Bearer' => []],
            ],
        ];

        foreach (scandir(dirname(__DIR__, 3).'/Models') as $filename) {
            if (! str_ends_with($filename, '.php')) {
                continue;
            }

            $modelname = substr($filename, 0, strlen($filename) - 4);
            $classname = 'Ledningssystemet\Ledningssystemet\\Models\\'.$modelname;

            if (! is_subclass_of($classname, Model::class)) {
                continue;
            }

            // Ensure pretty name exist
            if (! method_exists($classname, 'getPrettyName')) {
                continue;
            }

            // Get properties
            $editableProperties = [];
            $visibleProperties = [];

            $newobj = (new $classname);
            foreach ($newobj->getFillable() as $fieldname) {
                $editableProperties[$fieldname] = (object) [];
            }

            foreach ($newobj->getVisible() as $fieldname) {
                $visibleProperties[$fieldname] = (object) [];
            }

            $requestBody = [
                'name' => 'body',
                'in' => 'body',
                'required' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => (object) $editableProperties,
                ],
            ];

            $responseSchema = [
                'type' => 'object',
                'properties' => (object) $visibleProperties,
            ];

            // Swagger 2.0 has no oneOf; leave type open for the array and paginated object.
            $indexResponseSchema = [
                'description' => 'An array of items, or a pagination object with a data array when per_page is supplied.',
                'items' => $responseSchema,
                'properties' => [
                    'data' => ['type' => 'array', 'items' => $responseSchema],
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                ],
            ];

            $retval['tags'][] = ['name' => $modelname, 'description' => $classname::getPrettyName(true)];

            $retval['paths']['/api/v1/crud/'.$modelname] = [
                // Index
                'get' => [
                    'tags' => [
                        $modelname,
                    ],
                    'summary' => __('List all items'),
                    'description' => 'Filter using filter[field]=value. Allowed filters, sorts, fields and includes depend on the model. '
                        .'Unless overridden by the model, filters use visible database columns (exact matching for numeric and boolean fields, partial matching for text) '
                        .'and visible relationship paths. Computed attributes are not automatically filterable. '
                        .'Without per_page the response is an array; with per_page it is a pagination object containing data and pagination metadata.',
                    'parameters' => $this->getIndexParams($newobj),
                    'responses' => [
                        '200' => [
                            'description' => __('Successful operation'),
                            'schema' => $indexResponseSchema,
                        ],
                        '400' => [
                            'description' => 'Invalid filter, sort, field or include.',
                        ],
                        '401' => [
                            'description' => __('Unauthenticated'),
                        ],
                        '403' => [
                            'description' => __('Unauthorized'),
                        ],
                    ],
                ],

                // Create
                'post' => [
                    'tags' => [
                        $modelname,
                    ],
                    'summary' => __('Create a new').' '.$classname::getPrettyName(),
                    'description' => '',
                    'parameters' => [$requestBody],
                    'responses' => [
                        '200' => [
                            'description' => __('Successful operation'),
                            'schema' => $responseSchema,
                        ],
                        '401' => [
                            'description' => __('Unauthenticated'),
                        ],
                        '403' => [
                            'description' => __('Unauthorized'),
                        ],
                    ],
                ],
            ];

            $retval['paths']['/api/v1/crud/'.$modelname.'/{id}'] = [
                // View
                'get' => [
                    'tags' => [
                        $modelname,
                    ],
                    'summary' => __('View').' '.$classname::getPrettyName().' '.__('with specific id'),
                    'description' => '',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'description' => __('ID of object to view'),
                            'required' => true,
                            'type' => 'integer',
                            'format' => 'int64',
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => __('Successful operation'),
                            'schema' => $responseSchema,
                        ],
                        '401' => [
                            'description' => __('Unauthenticated'),
                        ],
                        '403' => [
                            'description' => __('Unauthorized'),
                        ],
                        '404' => [
                            'description' => __('Not found'),
                        ],
                    ],
                ],

                // Update
                'patch' => [
                    'tags' => [
                        $modelname,
                    ],
                    'summary' => __('Update').' '.$classname::getPrettyName().' '.__('with specific id'),
                    'description' => '',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'description' => __('ID of object to update'),
                            'required' => true,
                            'type' => 'integer',
                            'format' => 'int64',
                        ],
                        $requestBody,
                    ],
                    'responses' => [
                        '200' => [
                            'description' => __('Successful operation'),
                            'schema' => $responseSchema,
                        ],
                        '401' => [
                            'description' => __('Unauthenticated'),
                        ],
                        '403' => [
                            'description' => __('Unauthorized'),
                        ],
                        '404' => [
                            'description' => __('Not found'),
                        ],
                    ],
                ],

                // Delete
                'delete' => [
                    'tags' => [
                        $modelname,
                    ],
                    'summary' => __('Delete').' '.$classname::getPrettyName().' '.__('with specific id'),
                    'description' => '',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'description' => __('ID of object to delete'),
                            'required' => true,
                            'type' => 'integer',
                            'format' => 'int64',
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => __('Successful operation'),
                        ],
                        '401' => [
                            'description' => __('Unauthenticated'),
                        ],
                        '403' => [
                            'description' => __('Unauthorized'),
                        ],
                        '404' => [
                            'description' => __('Not found'),
                        ],
                    ],
                ],
            ];

            // Register custom actions defined in the model's $actions property
            if (property_exists($newobj, 'actions') && is_array($newobj->actions)) {
                foreach ($newobj->actions as $action) {
                    $retval['paths']['/api/v1/crud/'.$modelname.'/{id}/'.$action] = [
                        'post' => [
                            'tags' => [
                                $modelname,
                            ],
                            'summary' => ucfirst($action).' '.$classname::getPrettyName().' '.__('with specific id'),
                            'description' => '',
                            'parameters' => [
                                [
                                    'name' => 'id',
                                    'in' => 'path',
                                    'description' => __('ID of object'),
                                    'required' => true,
                                    'type' => 'integer',
                                    'format' => 'int64',
                                ],
                            ],
                            'responses' => [
                                '200' => [
                                    'description' => __('Successful operation'),
                                ],
                                '401' => [
                                    'description' => __('Unauthenticated'),
                                ],
                                '403' => [
                                    'description' => __('Unauthorized'),
                                ],
                                '404' => [
                                    'description' => __('Not found'),
                                ],
                            ],
                        ],
                    ];
                }
            }

        }

        return response()->json($retval);
    }
}
