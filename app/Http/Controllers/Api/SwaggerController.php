<?php

namespace Ledningssystemet\Ledningssystemet\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Controller;
use ReflectionMethod;

class SwaggerController extends Controller
{
    private function getIndexFilterParams(string $classname): array
    {
        $params = [];
        $reflection = new ReflectionMethod($classname, 'index');
        $fileLines = file($reflection->getFileName());
        $methodSource = implode('', array_slice($fileLines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
        preg_match_all("/request\(\)->(?:input|has)\(\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/", $methodSource, $matches);
        foreach ($matches[1] as $param) {
            if (! in_array($param, $params)) {
                $params[] = $param;
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
            $objstatus = null;
            if (strpos($filename, '.php') === false) {
                continue;
            }

            $modelname = substr($filename, 0, strlen($filename) - 4);
            $classname = 'Ledningssystemet\Ledningssystemet\\Models\\'.$modelname;

            // Ensure index function exist
            if (! method_exists($classname, 'index')) {
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

            // Build query parameters for the index endpoint from the model's index method
            $indexFilterParams = [];
            foreach ($this->getIndexFilterParams($classname) as $param) {
                $indexFilterParams[] = [
                    'name' => $param,
                    'in' => 'query',
                    'required' => false,
                    'type' => 'string',
                ];
            }

            $retval['tags'][] = ['name' => $modelname, 'description' => $classname::getPrettyName(true)];

            $retval['paths']['/api/v1/items/'.$modelname] = [
                // Index
                'get' => [
                    'tags' => [
                        $modelname,
                    ],
                    'summary' => __('List all items'),
                    'description' => '',
                    'parameters' => $indexFilterParams,
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

            $retval['paths']['/api/v1/items/'.$modelname.'/{id}'] = [
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
                    $retval['paths']['/api/v1/items/'.$modelname.'/{id}/'.$action] = [
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
