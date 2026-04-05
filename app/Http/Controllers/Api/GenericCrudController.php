<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GenericCrudIndexRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GenericCrudController extends Controller
{
    /**
     * @var array<class-string<Model>, array<string, mixed>>
     */
    private array $metadataCache = [];

    public function index(GenericCrudIndexRequest $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        Gate::authorize('viewAny', $modelClass);

        $metadata = $this->metadataFor($modelClass);

        $search = trim($request->string('search')->toString());

        $allowedFilters = [];

        foreach (array_keys($metadata['filterable']) as $column) {
            $allowedFilters[] = AllowedFilter::exact($column);
        }

        $query = QueryBuilder::for($modelClass::query())
            ->allowedFilters(...$allowedFilters)
            ->allowedSorts(...$metadata['selectable']);

        $this->applySearch(
            $query,
            $search,
            $metadata['searchable'],
            $metadata['searchable_relations']
        );

        $selectedColumns = $this->parseSelectedColumns($request, $metadata['selectable']);
        if ($selectedColumns !== []) {
            $query->select($selectedColumns);
        }

        $paginate = $request->has('paginate')
            ? $request->boolean('paginate')
            : (bool) config('generic_crud.default_paginate', false);

        if ($paginate) {
            $maxPerPage = (int) config('generic_crud.max_per_page', 100);
            $defaultPerPage = (int) config('generic_crud.default_per_page', 25);
            $perPage = $request->integer('per_page', $defaultPerPage);
            $perPage = max(1, min($maxPerPage, $perPage));

            return response()->json(
                $query->paginate($perPage)->appends($request->query())
            );
        }

        return response()->json($query->get());
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        Gate::authorize('create', $modelClass);

        $rules = $this->validationRulesFor($modelClass, false);
        $data = $rules === []
            ? $request->only((new $modelClass())->getFillable())
            : $request->validate($rules);

        $model = new $modelClass();
        $model->fill($data);
        if (method_exists($model, 'getHidden') && $model->getHidden() !== []) {
            // Some models validate via attributesToArray() in saving hooks; make hidden fields visible for that validation pass.
            $model->makeVisible($model->getHidden());
        }
        $model->save();

        return response()->json($model->fresh(), Response::HTTP_CREATED);
    }

    public function show(Request $request, string $resource, string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $model = $modelClass::query()->findOrFail($id);

        Gate::authorize('view', $model);

        $metadata = $this->metadataFor($modelClass);
        $selectedColumns = $this->parseSelectedColumns($request, $metadata['selectable']);

        if ($selectedColumns !== []) {
            $model = $modelClass::query()
                ->select($selectedColumns)
                ->findOrFail($id);
        }

        return response()->json($model);
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $model = $modelClass::query()->findOrFail($id);

        Gate::authorize('update', $model);

        $rules = $this->validationRulesFor($modelClass, true);
        $data = $rules === []
            ? $request->only($model->getFillable())
            : $request->validate($rules);

        $model->fill($data);
        if (method_exists($model, 'getHidden') && $model->getHidden() !== []) {
            // Keep hidden-but-required attributes available to model-level validation on update.
            $model->makeVisible($model->getHidden());
        }
        $model->save();

        return response()->json($model->fresh());
    }

    public function destroy(string $resource, string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $model = $modelClass::query()->findOrFail($id);

        Gate::authorize('delete', $model);
        $model->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @return class-string<Model>
     */
    private function resolveModelClass(string $resource): string
    {
        $configured = (array) config('generic_crud.resources', []);
        if (array_key_exists($resource, $configured)) {
            $modelClass = $configured[$resource];
        } else {
            $studlyResource = Str::studly(str_replace(['-', '.'], ' ', $resource));
            $modelClass = 'App\\Models\\'.Str::singular($studlyResource);
        }

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            abort(Response::HTTP_NOT_FOUND, 'Unknown resource.');
        }

        return $modelClass;
    }

    /**
     * @param class-string<Model> $modelClass
     * @return array<string, mixed>
     */
    private function metadataFor(string $modelClass): array
    {
        if (isset($this->metadataCache[$modelClass])) {
            return $this->metadataCache[$modelClass];
        }

        $model = new $modelClass();
        $describedModel = $this->describeModel($model);
        $columnTypes = $describedModel['types'];
        $columns = $describedModel['columns'];
        $selectable = $describedModel['selectable'];
        $numericTypes = ['tinyint', 'smallint', 'mediumint', 'integer', 'int', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real'];
        $booleanTypes = ['bool', 'boolean'];

        $filterable = [];

        foreach ($columns as $column) {
            $type = $columnTypes[$column] ?? 'string';

            if (in_array($type, $numericTypes, true) || in_array($type, $booleanTypes, true)) {
                $filterable[$column] = $type;
            }
        }

        $crudSearch = $this->resolveCrudSearch($model, $describedModel['searchable']);

        return $this->metadataCache[$modelClass] = [
            'types' => $columnTypes,
            'selectable' => $selectable,
            'filterable' => $filterable,
            'searchable' => $crudSearch['direct'],
            'searchable_relations' => $crudSearch['relations'],
        ];
    }

    /**
     * @return array{columns: array<int, string>, types: array<string, string>, selectable: array<int, string>, searchable: array<int, string>}
     */
    private function describeModel(Model $model): array
    {
        $table = $model->getTable();
        $connection = Schema::connection($model->getConnectionName());
        $columns = $connection->getColumnListing($table);

        $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];
        $columnTypes = [];

        foreach ($columns as $column) {
            $type = null;

            if (($casts[$column] ?? null) === 'bool' || ($casts[$column] ?? null) === 'boolean') {
                $type = 'boolean';
            }

            if ($type === null) {
                try {
                    $type = $connection->getColumnType($table, $column);
                } catch (Throwable) {
                    $type = 'string';
                }
            }

            $columnTypes[$column] = Str::lower((string) $type);
        }

        $visible = method_exists($model, 'getVisible') ? $model->getVisible() : [];
        $hidden = method_exists($model, 'getHidden') ? $model->getHidden() : [];

        $selectable = $visible !== []
            ? array_values(array_intersect($columns, $visible))
            : array_values(array_diff($columns, $hidden));

        return [
            'columns' => $columns,
            'types' => $columnTypes,
            'selectable' => $selectable,
            'searchable' => $this->textSearchableColumns($selectable, $columnTypes),
        ];
    }

    /**
     * @param array<int, string> $selectableColumns
     * @param array<string, string> $columnTypes
     * @return array<int, string>
     */
    private function textSearchableColumns(array $selectableColumns, array $columnTypes): array
    {
        $textTypes = ['char', 'string', 'text', 'tinytext', 'mediumtext', 'longtext', 'varchar'];

        return array_values(array_filter(
            $selectableColumns,
            static fn (string $column): bool => in_array($columnTypes[$column] ?? 'string', $textTypes, true)
        ));
    }

    /**
     * @param array<int, string> $defaultSearchable
     * @return array{direct: array<int, string>, relations: array<string, array<int, string>>}
     */
    private function resolveCrudSearch(Model $model, array $defaultSearchable): array
    {
        $modelClass = $model::class;
        if (! method_exists($modelClass, 'crudSearch')) {
            return [
                'direct' => $defaultSearchable,
                'relations' => [],
            ];
        }

        $configuredSearch = $modelClass::crudSearch();
        if (! is_array($configuredSearch)) {
            return [
                'direct' => $defaultSearchable,
                'relations' => [],
            ];
        }

        $allowedDirectColumns = array_fill_keys($defaultSearchable, true);
        $configuredDirect = $configuredSearch['direct'] ?? $defaultSearchable;
        if (! is_array($configuredDirect)) {
            $configuredDirect = $defaultSearchable;
        }

        $direct = [];
        foreach ($configuredDirect as $column) {
            if (! is_string($column) || ! isset($allowedDirectColumns[$column])) {
                continue;
            }

            $direct[] = $column;
        }

        return [
            'direct' => array_values(array_unique($direct)),
            'relations' => $this->normalizeCrudSearchRelations($model, $configuredSearch['relations'] ?? []),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function normalizeCrudSearchRelations(Model $model, mixed $configuredRelations): array
    {
        if (! is_array($configuredRelations)) {
            return [];
        }

        $normalizedRelations = [];

        foreach ($configuredRelations as $relationPath => $columns) {
            if (! is_string($relationPath) || trim($relationPath) === '' || ! is_array($columns)) {
                continue;
            }

            $relatedModel = $this->resolveRelatedModelForPath($model, $relationPath);
            if (! $relatedModel instanceof Model) {
                continue;
            }

            $allowedColumns = array_fill_keys($this->describeModel($relatedModel)['searchable'], true);
            $validColumns = [];

            foreach ($columns as $column) {
                if (! is_string($column) || ! isset($allowedColumns[$column])) {
                    continue;
                }

                $validColumns[] = $column;
            }

            if ($validColumns === []) {
                continue;
            }

            $normalizedRelations[$relationPath] = array_values(array_unique($validColumns));
        }

        return $normalizedRelations;
    }

    private function resolveRelatedModelForPath(Model $model, string $relationPath): ?Model
    {
        $currentModel = $model;

        foreach (explode('.', $relationPath) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || ! method_exists($currentModel, $segment)) {
                return null;
            }

            try {
                $relation = $currentModel->{$segment}();
            } catch (Throwable) {
                return null;
            }

            if (! $relation instanceof Relation) {
                return null;
            }

            $currentModel = $relation->getRelated();
        }

        return $currentModel;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function applyFilters(mixed $query, mixed $filters, array $metadata): void
    {
        if (! is_array($filters) || $filters === []) {
            return;
        }

        $allowedFilters = $metadata['filterable'];

        foreach ($filters as $field => $value) {
            if (! is_string($field) || ! array_key_exists($field, $allowedFilters)) {
                throw ValidationException::withMessages([
                    'filter' => ["Filter field [{$field}] is not allowed."],
                ]);
            }

            $type = $allowedFilters[$field];
            $normalized = $this->normalizeFilterValue($field, $type, $value);
            $query->where($field, $normalized);
        }
    }

    /**
     * @param array<int, string> $searchableColumns
     * @param array<string, array<int, string>> $searchableRelations
     */
    private function applySearch(mixed $query, string $search, array $searchableColumns, array $searchableRelations = []): void
    {
        if ($search === '' || ($searchableColumns === [] && $searchableRelations === [])) {
            return;
        }

        $query->where(function ($subQuery) use ($search, $searchableColumns, $searchableRelations): void {
            foreach ($searchableColumns as $column) {
                $subQuery->orWhere($column, 'like', "%{$search}%");
            }

            foreach ($searchableRelations as $relationPath => $columns) {
                $subQuery->orWhereHas($relationPath, function ($relationQuery) use ($search, $columns): void {
                    $relationQuery->where(function ($nestedQuery) use ($search, $columns): void {
                        foreach ($columns as $column) {
                            $nestedQuery->orWhere($column, 'like', "%{$search}%");
                        }
                    });
                });
            }
        });
    }

    /**
     * @param array<int, string> $selectableColumns
     * @return array<int, string>
     */
    private function parseSelectedColumns(Request $request, array $selectableColumns): array
    {
        $raw = $request->query('$select', $request->query('select', ''));
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $columns = array_values(array_filter(array_map('trim', explode(',', $raw))));

        foreach ($columns as $column) {
            if (! in_array($column, $selectableColumns, true)) {
                throw ValidationException::withMessages([
                    '$select' => ["Selected field [{$column}] is not allowed."],
                ]);
            }
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRulesFor(string $modelClass, bool $forUpdate): array
    {
        if (! method_exists($modelClass, 'validationRules')) {
            return [];
        }

        $rules = $modelClass::validationRules();
        if (! is_array($rules)) {
            return [];
        }

        if (! $forUpdate) {
            return $rules;
        }

        foreach ($rules as $field => $fieldRules) {
            if (! is_array($fieldRules)) {
                continue;
            }

            $filteredRules = array_values(array_filter($fieldRules, static fn (mixed $rule): bool => $rule !== 'required'));
            if (! in_array('sometimes', $filteredRules, true)) {
                array_unshift($filteredRules, 'sometimes');
            }

            $rules[$field] = $filteredRules;
        }

        return $rules;
    }

    private function normalizeFilterValue(string $field, string $type, mixed $value): mixed
    {
        if (in_array($type, ['boolean', 'bool'], true)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($normalized === null) {
                throw ValidationException::withMessages([
                    'filter' => ["Filter value for [{$field}] must be boolean."],
                ]);
            }

            return $normalized;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'filter' => ["Filter value for [{$field}] must be numeric."],
            ]);
        }

        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'integer', 'int', 'bigint'], true)) {
            return (int) $value;
        }

        return (float) $value;
    }
}


