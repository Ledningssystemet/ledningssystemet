<?php

namespace App\Support\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class CrudResourceCatalog
{
    /**
     * @return array<int, array{resource: string, model: class-string<Model>}>
     */
    public function all(): array
    {
        $items = [];

        foreach ($this->configuredResources() as $resource => $modelClass) {
            $items[$resource] = [
                'resource' => $resource,
                'model' => $modelClass,
            ];
        }

        ksort($items);

        return array_values($items);
    }

    /**
     * @return array<int, string>
     */
    public function resourceNames(): array
    {
        return array_values(array_map(
            static fn (array $item): string => $item['resource'],
            $this->all()
        ));
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function configuredResources(): array
    {
        $configured = (array) config('generic_crud.resources', []);
        $resources = [];

        foreach ($configured as $resource => $modelClass) {
            if (! is_string($resource) || ! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $resources[$resource] = $modelClass;
        }

        return $resources;
    }
}

