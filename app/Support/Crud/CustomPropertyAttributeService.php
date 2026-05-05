<?php

namespace App\Support\Crud;

use App\Models\CustomProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomPropertyAttributeService
{
    /**
     * @var array<string, string>
     */
    private const TYPE_RESOURCE_MAP = [
        'user' => 'users',
        'department' => 'departments',
        'supplier' => 'suppliers',
        'customer' => 'customers',
        'asset' => 'assets',
        'process' => 'processes',
    ];

    /**
     * @param class-string<Model> $modelClass
     */
    public function supportsCustomProperties(string $modelClass): bool
    {
        return method_exists($modelClass, 'int_custom_property_object_as_object');
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<int, string> $reservedKeys
     * @return array<string, array{id: int, name: string, type: string, required: bool, user_editable: bool, ordinal: int, resource?: string}>
     */
    public function definitionsFor(string $modelClass, array $reservedKeys = []): array
    {
        if (! $this->supportsCustomProperties($modelClass)) {
            return [];
        }

        $reserved = array_fill_keys($reservedKeys, true);
        $definitions = [];

        $properties = CustomProperty::query()
            ->where('context', $modelClass)
            ->orderBy('ordinal')
            ->orderBy('id')
            ->get(['id', 'name', 'type', 'required', 'user_editable', 'ordinal']);

        foreach ($properties as $property) {
            $baseKey = Str::snake(trim((string) $property->name));
            if ($baseKey === '') {
                $baseKey = 'custom_property_'.$property->id;
            }

            $key = $baseKey;
            if (isset($reserved[$key]) || isset($definitions[$key])) {
                $key = $baseKey.'_'.$property->id;
            }

            while (isset($reserved[$key]) || isset($definitions[$key])) {
                $key .= '_value';
            }

            $definitions[$key] = [
                'id' => (int) $property->id,
                'name' => (string) $property->name,
                'type' => Str::lower((string) $property->type),
                'required' => (bool) $property->required,
                'user_editable' => (bool) $property->user_editable,
                'ordinal' => (int) $property->ordinal,
            ];

            $resource = self::TYPE_RESOURCE_MAP[$definitions[$key]['type']] ?? null;
            if (is_string($resource)) {
                $definitions[$key]['resource'] = $resource;
            }
        }

        return $definitions;
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<int, string> $reservedKeys
     * @return array<int, array{key: string, id: int, name: string, type: string, required: bool, user_editable: bool, ordinal: int, resource?: string}>
     */
    public function metadataForModel(string $modelClass, array $reservedKeys = []): array
    {
        $definitions = $this->definitionsFor($modelClass, $reservedKeys);
        $rows = [];

        foreach ($definitions as $key => $definition) {
            $rows[] = [
                'key' => $key,
                'id' => (int) $definition['id'],
                'name' => (string) ($definition['name'] ?? $key),
                'type' => (string) ($definition['type'] ?? 'string'),
                'required' => (bool) ($definition['required'] ?? false),
                'user_editable' => (bool) ($definition['user_editable'] ?? true),
                'ordinal' => (int) ($definition['ordinal'] ?? 0),
                'resource' => isset($definition['resource']) && is_string($definition['resource'])
                    ? $definition['resource']
                    : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($a['ordinal'] <=> $b['ordinal']) ?: ($a['id'] <=> $b['id']));

        return $rows;
    }

    /**
     * @param array<string, array{id: int, type: string, required: bool}> $definitions
     */
    public function hydrateResult(mixed $result, array $definitions): void
    {
        if ($definitions === []) {
            return;
        }

        if ($result instanceof Model) {
            $this->hydrateModel($result, $definitions);

            return;
        }

        if ($result instanceof AbstractPaginator) {
            $result->setCollection(
                $result->getCollection()->map(function (mixed $item) use ($definitions): mixed {
                    if ($item instanceof Model) {
                        $this->hydrateModel($item, $definitions);
                    }

                    return $item;
                })
            );

            return;
        }

        if ($result instanceof Collection) {
            $result->each(function (mixed $item) use ($definitions): void {
                if ($item instanceof Model) {
                    $this->hydrateModel($item, $definitions);
                }
            });
        }
    }

    /**
     * @param array<string, array{id: int, type: string, required: bool}> $definitions
     * @return array<string, array<int, string>>
     */
    public function validationRules(array $definitions, bool $forUpdate): array
    {
        $rules = [];

        foreach ($definitions as $key => $definition) {
            $fieldRules = [];

            if ($forUpdate) {
                $fieldRules[] = 'sometimes';
            }

            if (! $forUpdate && ($definition['required'] ?? false)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            foreach ($this->typeValidationRules((string) ($definition['type'] ?? 'string')) as $rule) {
                $fieldRules[] = $rule;
            }

            $rules[$key] = array_values(array_unique($fieldRules));
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $customData
     * @param array<string, array{id: int, type: string, required: bool}> $definitions
     */
    public function syncCustomProperties(Model $model, array $customData, array $definitions): void
    {
        if ($customData === []) {
            return;
        }

        $relation = $this->customPropertyRelation($model);
        if (! $relation instanceof MorphMany) {
            return;
        }

        foreach ($definitions as $key => $definition) {
            if (! array_key_exists($key, $customData)) {
                continue;
            }

            $customPropertyId = (int) ($definition['id'] ?? 0);
            if ($customPropertyId <= 0) {
                continue;
            }

            $value = $customData[$key];

            if ($value === null || $value === '') {
                $relation->where('custom_property_id', $customPropertyId)->delete();

                continue;
            }

            $normalizedValue = $this->normalizeStoredValue($value, (string) ($definition['type'] ?? 'string'));

            $relation->updateOrCreate(
                ['custom_property_id' => $customPropertyId],
                ['value' => $normalizedValue]
            );
        }
    }

    /**
     * @param array<string, array{id: int, type: string, required: bool}> $definitions
     */
    private function hydrateModel(Model $model, array $definitions): void
    {
        $relation = $this->customPropertyRelation($model);
        if (! $relation instanceof MorphMany) {
            return;
        }

        $customPropertyIds = array_values(array_map(
            static fn (array $definition): int => (int) $definition['id'],
            $definitions
        ));

        if ($customPropertyIds === []) {
            return;
        }

        $customValues = $model->relationLoaded('int_custom_property_object_as_object')
            ? $model->getRelation('int_custom_property_object_as_object')
            : $relation
                ->whereIn('custom_property_id', $customPropertyIds)
                ->get(['custom_property_id', 'value']);

        $valueMap = [];
        foreach ($customValues as $customValue) {
            $valueMap[(int) ($customValue->custom_property_id ?? 0)] = $customValue->value;
        }

        foreach ($definitions as $key => $definition) {
            $value = $valueMap[(int) $definition['id']] ?? null;
            $model->setAttribute($key, $this->normalizeResponseValue($value, (string) ($definition['type'] ?? 'string')));
        }
    }

    /**
     * @return array<int, string>
     */
    private function typeValidationRules(string $type): array
    {
        return match (Str::lower($type)) {
            'boolean' => ['boolean'],
            'user' => ['integer', 'exists:users,id'],
            'department' => ['integer', 'exists:departments,id'],
            'supplier' => ['integer', 'exists:suppliers,id'],
            'customer' => ['integer', 'exists:customers,id'],
            'asset' => ['integer', 'exists:assets,id'],
            'process' => ['integer', 'exists:processes,id'],
            default => ['string'],
        };
    }

    private function normalizeStoredValue(mixed $value, string $type): string
    {
        $type = Str::lower($type);

        if ($type === 'boolean') {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $normalized ? '1' : '0';
        }

        if (in_array($type, ['user', 'department', 'supplier', 'customer', 'asset', 'process'], true)) {
            return (string) ((int) $value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }

    private function normalizeResponseValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = Str::lower($type);

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if (in_array($type, ['user', 'department', 'supplier', 'customer', 'asset', 'process'], true)) {
            return is_numeric($value) ? (int) $value : null;
        }

        return (string) $value;
    }

    private function customPropertyRelation(Model $model): ?MorphMany
    {
        if (! method_exists($model, 'int_custom_property_object_as_object')) {
            return null;
        }

        $relation = $model->int_custom_property_object_as_object();

        return $relation instanceof MorphMany ? $relation : null;
    }
}

