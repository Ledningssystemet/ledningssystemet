<?php

namespace Ledningssystemet\Ledningssystemet\Support;

use Illuminate\Database\Eloquent\Model;

final class TagMorphType
{
    /**
     * @return array<int, string>
     */
    public static function forModel(Model|string $model): array
    {
        $class = is_string($model) ? $model : $model::class;
        $name = class_basename($class);

        return array_values(array_unique([
            $class,
            'App\\Models\\'.$name,
            'Ledningssystemet\\Ledningssystemet\\Models\\'.$name,
        ]));
    }

    public static function resolve(string $type): ?string
    {
        foreach (self::forModel($type) as $class) {
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                return $class;
            }
        }

        return null;
    }
}
