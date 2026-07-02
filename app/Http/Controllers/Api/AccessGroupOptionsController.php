<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AccessGroupOptionsController extends Controller
{
    public function claims(): JsonResponse
    {
        Gate::authorize('viewAny', AccessGroup::class);

        $claims = AccessGroup::allClaims();

        $rows = [];
        foreach ($claims as $claim => $label) {
            $rows[] = [
                'id' => $claim,
                'name' => $label,
            ];
        }

        return response()->json($rows);
    }
}

