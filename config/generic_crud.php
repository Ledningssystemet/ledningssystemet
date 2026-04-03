<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Resource map
    |--------------------------------------------------------------------------
    |
    | Optional explicit resource-to-model mapping. If empty, the endpoint tries
    | App\Models\{SingularStudly(resource)}.
    |
    */
    'resources' => [
        // 'users' => App\Models\User::class,
    ],

    'default_paginate' => false,
    'default_per_page' => 25,
    'max_per_page' => 100,
];

