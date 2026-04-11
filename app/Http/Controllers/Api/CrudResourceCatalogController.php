<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Crud\CrudResourceCatalog;
use Illuminate\Http\JsonResponse;

class CrudResourceCatalogController extends Controller
{
    public function index(CrudResourceCatalog $catalog): JsonResponse
    {
        return response()->json([
            'data' => $catalog->all(),
        ]);
    }
}

