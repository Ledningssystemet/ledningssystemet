<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomProperty;
use App\Support\Crud\CrudResourceCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CustomPropertyContextController extends Controller
{
    public function index(CrudResourceCatalog $catalog): JsonResponse
    {
        Gate::authorize('viewAny', CustomProperty::class);

        $contexts = [];

        foreach ($catalog->all() as $resource) {
            $modelClass = $resource['model'] ?? null;

            if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $label = $this->resolveLabel($modelClass, (string) ($resource['resource'] ?? ''));

            if (isset($contexts[$modelClass])) {
                continue;
            }

            $contexts[$modelClass] = [
                'resource' => (string) ($resource['resource'] ?? ''),
                'context' => $modelClass,
                'label' => $label,
            ];
        }

        $contexts = array_values($contexts);
        usort($contexts, static fn (array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label']));

        return response()->json([
            'data' => array_values($contexts),
        ]);
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function resolveLabel(string $modelClass, string $fallbackResource): string
    {
        if (method_exists($modelClass, 'getPrettyName')) {
            $label = $modelClass::getPrettyName(true);

            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        if ($fallbackResource !== '') {
            return Str::headline($fallbackResource);
        }

        return class_basename($modelClass);
    }
}

