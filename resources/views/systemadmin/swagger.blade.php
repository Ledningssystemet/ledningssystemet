<?php
if(!auth()->user()->canAny(['systemadministrator.edit']))
   abort(403);

// Load swagger json?
if(request()->has('json'))
{
   // Helper: extract query filter parameters from a model's index method source code
   function getIndexFilterParams($classname) {
      $params = [];
      try {
         $reflection = new ReflectionMethod($classname, 'index');
         $fileLines = file($reflection->getFileName());
         $methodSource = implode('', array_slice($fileLines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
         preg_match_all("/request\(\)->(?:input|has)\(\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/", $methodSource, $matches);
         foreach($matches[1] as $param)
            if(!in_array($param, $params))
               $params[] = $param;
      } catch(Exception $e) {}
      return $params;
   }

   $retval = [
      'swagger' => '2.0',
      'info' => [
         'title' => 'Ledningssystemet.se Open API',
      ],
      'host' => $_SERVER['HTTP_HOST'],
      'basePath' => '',
      'tags' => [
      ],
      'schemes' => [
         (0 === strpos(config('app.url'), 'https')) ? 'https' : 'http',
      ],
      'securityDefinitions' => [
         'Bearer' => [
            'type' => 'apiKey',
            'name' => 'Authorization',
            'in' => 'header',
         ]
      ],
      'paths' => [
      ],
      'security' => [
         'Bearer' => [],
      ]
   ];
 
 
   foreach(scandir(__DIR__.'/../../../app/Models') as $filename)
   {
      $objstatus = null;
      if(false === strpos($filename, '.php'))
         continue;
    
      $modelname = substr($filename, 0, strlen($filename)-4);
      $classname = 'App\\Models\\'.$modelname;
      
      // Ensure index function exist
      if(!method_exists($classname, 'index'))
         continue;
      
      // Ensure pretty name exist
      if(!method_exists($classname, 'getPrettyName'))
         continue;

      // Get properties
      $editableProperties = [];
      $visibleProperties = [];
      
      $newobj = (new $classname);
      foreach($newobj->getFillable() as $fieldname)
      {
         $editableProperties[] = [
            'name' => $fieldname,
            'in' => 'body',
         ];
      }      
      
      foreach($newobj->getVisible() as $fieldname)
      {
         $visibleProperties[$fieldname] = [];
      }

      // Build query parameters for the index endpoint from the model's index method
      $indexFilterParams = [];
      foreach(getIndexFilterParams($classname) as $param)
      {
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
               $modelname
            ],
            'summary' => __('List all items'),
            'description' => '',
            'parameters' => $indexFilterParams,
            'responses' => [
               '200' => [
                  'description' => __('Successful operation'),
                  'schema' => ['type' => 'object', 'properties' => $visibleProperties],
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
               $modelname
            ],
            'summary' => __('Create a new').' '.$classname::getPrettyName(),
            'description' => '',
            'parameters' => $editableProperties
         ]
      ];
      
      $retval['paths']['/api/v1/items/'.$modelname.'/{id}'] = [
         // View
         'get' => [
            'tags' => [
               $modelname
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
                  'format' => 'int64'
               ]
            ],
            'responses' => [
               '200' => [
                  'description' => __('Successful operation'),
                  'schema' => ['type' => 'object', 'properties' => $visibleProperties],
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
               $modelname
            ],
            'summary' => __('Update').' '.$classname::getPrettyName().' '.__('with specific id'),
            'description' => '',
            'parameters' => $editableProperties
         ],
         
         // Delete
         'delete' => [
            'tags' => [
               $modelname
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
                  'format' => 'int64'
               ]
            ],
         ]
      ];

      // Register custom actions defined in the model's $actions property
      if(property_exists($newobj, 'actions') && is_array($newobj->actions))
      {
         foreach($newobj->actions as $action)
         {
            $retval['paths']['/api/v1/items/'.$modelname.'/{id}/'.$action] = [
               'post' => [
                  'tags' => [
                     $modelname
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
                        'format' => 'int64'
                     ]
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
               ]
            ];
         }
      }
      
   }
   
   die(json_encode($retval));
}
?>


@extends('layouts.master')
@section('container')
<link rel="stylesheet" href="/vendors/swagger/swagger-ui.css" />
<script src="/vendors/swagger/swagger-ui-bundle.js" crossorigin></script>
<script src="/vendors/swagger/swagger-ui-standalone-preset.js" crossorigin></script>

<script>
$(function(){
   SwaggerUIBundle({
      url: '/systemadmin/swagger?json',
      dom_id: '#swaggerContainer',
   }); 
});
</script>
<div id="swaggerContainer"></div>
   
@endsection
