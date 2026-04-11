<?php

namespace App\Http\Controllers;

use App\Support\OpenApi\OpenApiSpecBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpenApiDocumentationController extends Controller
{
    public function show(Request $request): View
    {
        return view('api-docs', [
            'embedded' => $request->boolean('embedded'),
            'specUrl' => route('openapi.spec'),
        ]);
    }

    public function spec(OpenApiSpecBuilder $builder): JsonResponse
    {
        return response()->json($builder->build());
    }
}

