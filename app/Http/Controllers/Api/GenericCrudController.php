<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GenericCrudIndexRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
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

        $allowedFilters = $this->allowedFiltersFor($metadata);

        $query = QueryBuilder::for($modelClass::query())
            ->allowedFilters(...$allowedFilters)
            ->allowedSorts(...$this->allowedSortsFor($modelClass, $metadata['selectable']));

        ['with' => $with, 'withCount' => $withCount] = $this->parseExtends($request, $modelClass);

        $this->applySearch(
            $query,
            $search,
            $metadata['searchable'],
            $metadata['searchable_relations']
        );

        if (method_exists($modelClass, 'applyCrudIndexFilters')) {
            $modelClass::applyCrudIndexFilters($query, $request);
        }

        $selectedColumns = $this->parseSelectedColumns($request, $metadata['selectable']);
        $selectedAppends = $this->parseSelectedAppends($request, $metadata['appendable']);
        if ($selectedColumns !== []) {
            $selectedColumns = $this->ensurePrimaryKeySelected($modelClass, $selectedColumns);
            $query->select($selectedColumns);
        }

        if ($with !== []) {
            $query->with($with);
        }

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        $paginate = $request->has('paginate')
            ? $request->boolean('paginate')
            : (bool) config('generic_crud.default_paginate', false);

        if ($paginate) {
            $maxPerPage = (int) config('generic_crud.max_per_page', 100);
            $defaultPerPage = (int) config('generic_crud.default_per_page', 25);
            $perPage = $request->integer('per_page', $defaultPerPage);
            $perPage = max(1, min($maxPerPage, $perPage));

            $result = $query->paginate($perPage)->appends($request->query());
            $this->appendSelectedAttributes($result, $selectedAppends);
            $this->filterResultAttributes($result, $selectedColumns, $selectedAppends, $with, $withCount);

            return response()->json($result);
        }

        if($request->has('limit')) {
            $limit = $request->integer('limit', 100);
            $query->limit($limit);
        }

        $result = $query->get();
        $this->appendSelectedAttributes($result, $selectedAppends);
        $this->filterResultAttributes($result, $selectedColumns, $selectedAppends, $with, $withCount);

        return response()->json($result);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        Gate::authorize('create', $modelClass);

        $rules = $this->validationRulesFor($modelClass, false);
        $data = $rules === []
            ? $request->only((new $modelClass())->getFillable())
            : $request->validate($rules);

        $model = DB::transaction(function () use ($modelClass, $data): Model {
            $createdModel = new $modelClass();
            $createdModel->fill($data);
            if (method_exists($createdModel, 'getHidden') && $createdModel->getHidden() !== []) {
                // Some models validate via attributesToArray() in saving hooks; make hidden fields visible for that validation pass.
                $createdModel->makeVisible($createdModel->getHidden());
            }
            $createdModel->save();

            return $createdModel;
        });

        return response()->json($model->fresh(), Response::HTTP_CREATED);
    }

    public function show(Request $request, string $resource, string $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        $model = $modelClass::query()->findOrFail($id);

        Gate::authorize('view', $model);

        $metadata = $this->metadataFor($modelClass);
        $selectedColumns = $this->parseSelectedColumns($request, $metadata['selectable']);
        $selectedAppends = $this->parseSelectedAppends($request, $metadata['appendable']);
        ['with' => $with, 'withCount' => $withCount] = $this->parseExtends($request, $modelClass);

        if ($selectedColumns !== []) {
            $selectedColumns = $this->ensurePrimaryKeySelected($modelClass, $selectedColumns);
        }

        if ($selectedColumns !== [] || $with !== [] || $withCount !== []) {
            $modelQuery = $modelClass::query();

            if ($selectedColumns !== []) {
                $modelQuery->select($selectedColumns);
            }

            if ($with !== []) {
                $modelQuery->with($with);
            }

            if ($withCount !== []) {
                $modelQuery->withCount($withCount);
            }

            $model = $modelQuery->findOrFail($id);
        }

        $this->appendSelectedAttributes($model, $selectedAppends);
        $this->filterResultAttributes($model, $selectedColumns, $selectedAppends, $with, $withCount);

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
            abort(Response::HTTP_NOT_FOUND, __('api.generic_crud.unknown_resource'));
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
            'appendable' => $this->resolveAppendableAttributes($model),
            'filterable' => $filterable,
            'searchable' => $crudSearch['direct'],
            'searchable_relations' => $crudSearch['relations'],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<int, AllowedFilter>
     */
    private function allowedFiltersFor(array $metadata): array
    {
        $allowedFilters = [];

        foreach ($metadata['selectable'] as $column) {
            if (! is_string($column) || trim($column) === '') {
                continue;
            }

            $allowedFilters[] = AllowedFilter::callback($column, function (mixed $query, mixed $value, string $property) use ($metadata): void {
                $this->applyFilters($query, [$property => $value], $metadata);
            });
        }

        return $allowedFilters;
    }

    /**
     * @return array<int, string>
     */
    private function resolveAppendableAttributes(Model $model): array
    {
        $configuredAppends = [];
        $modelClass = $model::class;

        if (method_exists($modelClass, 'crudAppends')) {
            $resolved = $modelClass::crudAppends();
            if (is_array($resolved)) {
                $configuredAppends = $resolved;
            }
        }

        $defaultAppends = method_exists($model, 'getAppends') ? $model->getAppends() : [];

        return array_values(array_unique(array_filter(
            array_merge($defaultAppends, $configuredAppends),
            static fn (mixed $append): bool => is_string($append) && trim($append) !== ''
        )));
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
        $allowedNullableColumns = array_fill_keys($metadata['selectable'] ?? [], true);

        foreach ($filters as $field => $value) {
            if (! is_string($field) || ! array_key_exists($field, $allowedNullableColumns)) {
                throw ValidationException::withMessages([
                    'filter' => [__('api.generic_crud.filter_field_not_allowed', ['field' => $field])],
                ]);
            }

            $nullFilter = $this->normalizeNullFilterValue($value);
            if ($nullFilter === 'null') {
                $query->whereNull($field);

                continue;
            }

            if ($nullFilter === 'not_null') {
                $query->whereNotNull($field);

                continue;
            }

            if (! array_key_exists($field, $allowedFilters)) {
                throw ValidationException::withMessages([
                    'filter' => [__('api.generic_crud.filter_field_not_allowed', ['field' => $field])],
                ]);
            }

            $type = $allowedFilters[$field];
            $normalized = $this->normalizeFilterValue($field, $type, $value);
            $query->where($field, $normalized);
        }
    }

    private function normalizeNullFilterValue(mixed $value): ?string
    {
        if ($value === null) {
            return 'null';
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = Str::lower(trim($value));
        if (in_array($normalized, ['null', 'is_null', 'is:null'], true)) {
            return 'null';
        }

        if (in_array($normalized, ['not_null', 'is_not_null', 'is:not_null', '!null'], true)) {
            return 'not_null';
        }

        return null;
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

        return array_values(array_unique(array_intersect($columns, $selectableColumns)));
    }

    /**
     * @param array<int, string> $appendableAttributes
     * @return array<int, string>
     */
    private function parseSelectedAppends(Request $request, array $appendableAttributes): array
    {
        $raw = $request->query('$select', $request->query('select', ''));
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $columns = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return array_values(array_unique(array_intersect($columns, $appendableAttributes)));
    }

    /**
     * @param array<int, string> $selectedAppends
     */
    private function appendSelectedAttributes(mixed $result, array $selectedAppends): void
    {
        if ($selectedAppends === []) {
            return;
        }

        if ($result instanceof Model) {
            $result->append($selectedAppends);

            return;
        }

        if ($result instanceof AbstractPaginator) {
            $result->setCollection(
                $result->getCollection()->map(function (mixed $item) use ($selectedAppends): mixed {
                    if ($item instanceof Model) {
                        $item->append($selectedAppends);
                    }

                    return $item;
                })
            );

            return;
        }

        if ($result instanceof Collection) {
            $result->each(function (mixed $item) use ($selectedAppends): void {
                if ($item instanceof Model) {
                    $item->append($selectedAppends);
                }
            });
        }
    }

    /**
     * @param class-string<Model> $modelClass
     * @return array{with: array<int, string>, withCount: array<int, string>}
     */
    private function parseExtends(Request $request, string $modelClass): array
    {
        $raw = $request->query('extends', $request->query('extend', ''));
        if (! is_string($raw) || trim($raw) === '') {
            return ['with' => [], 'withCount' => []];
        }

        $tokens = array_values(array_unique(array_filter(array_map(
            static fn (string $token): string => Str::lower(trim($token)),
            explode(',', $raw)
        ))));

        if ($tokens === []) {
            return ['with' => [], 'withCount' => []];
        }

        $available = $this->availableExtendsFor($modelClass);
        $with = [];
        $withCount = [];

        foreach ($tokens as $token) {
            if (Str::endsWith($token, '_count')) {
                $baseToken = substr($token, 0, -6);
                $relation = $available[$baseToken] ?? null;

                if ($relation === null) {
                    throw ValidationException::withMessages([
                        'extends' => [__('api.generic_crud.extend_not_allowed', ['extend' => $token])],
                    ]);
                }

                $withCount[] = $relation.' as '.$token;

                continue;
            }

            $relation = $available[$token] ?? null;
            if ($relation === null) {
                throw ValidationException::withMessages([
                    'extends' => [__('api.generic_crud.extend_not_allowed', ['extend' => $token])],
                ]);
            }

            $with[] = $relation;
        }

        return [
            'with' => array_values(array_unique($with)),
            'withCount' => array_values(array_unique($withCount)),
        ];
    }

    /**
     * @param class-string<Model> $modelClass
     * @return array<string, string>
     */
    private function availableExtendsFor(string $modelClass): array
    {
        $model = new $modelClass();

        $resolved = [];

        $resolved['messages'] = $this->resolveRelationMethod($model, ['messages', 'int_object_messages_as_object']);
        $resolved['message'] = $resolved['messages'];
        $resolved['object_messages'] = $resolved['messages'];

        $resolved['tags'] = $this->resolveRelationMethod($model, ['int_object_tags_as_object_tags', 'tags']);
        $resolved['tag'] = $resolved['tags'];
        $resolved['object_tags'] = $resolved['tags'];

        $resolved['history'] = $this->resolveRelationMethod($model, ['history', 'int_object_histories_as_object']);
        $resolved['histories'] = $resolved['history'];
        $resolved['object_histories'] = $resolved['history'];

        return array_filter($resolved, static fn (mixed $relation): bool => is_string($relation) && $relation !== '');
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<int, string> $defaultSorts
     * @return array<int, string|AllowedSort>
     */
    private function allowedSortsFor(string $modelClass, array $defaultSorts): array
    {
        $allowedSorts = $defaultSorts;

        if (! method_exists($modelClass, 'crudSorts')) {
            return $allowedSorts;
        }

        $configuredSorts = $modelClass::crudSorts();
        if (! is_array($configuredSorts)) {
            return $allowedSorts;
        }

        foreach ($configuredSorts as $sort) {
            if (is_string($sort) && trim($sort) !== '') {
                $allowedSorts[] = $sort;
                continue;
            }

            if ($sort instanceof AllowedSort) {
                $allowedSorts[] = $sort;
            }
        }

        return $allowedSorts;
    }

    private function resolveRelationMethod(Model $model, array $candidates): ?string
    {
        foreach ($candidates as $method) {
            if (! is_string($method) || ! method_exists($model, $method)) {
                continue;
            }

            try {
                $relation = $model->{$method}();
            } catch (Throwable) {
                continue;
            }

            if ($relation instanceof Relation) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<int, string> $selectedColumns
     * @return array<int, string>
     */
    private function ensurePrimaryKeySelected(string $modelClass, array $selectedColumns): array
    {
        $primaryKey = (new $modelClass())->getKeyName();
        if (! in_array($primaryKey, $selectedColumns, true)) {
            $selectedColumns[] = $primaryKey;
        }

        return array_values(array_unique($selectedColumns));
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
                    'filter' => [__('api.generic_crud.filter_value_boolean', ['field' => $field])],
                ]);
            }

            return $normalized;
        }

        // Convert current_user to the current user's ID if applicable'
        if ($value === 'current_user') {
            $value = auth()->id();
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'filter' => [__('api.generic_crud.filter_value_numeric', ['field' => $field])],
            ]);
        }

        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'integer', 'int', 'bigint'], true)) {
            return (int) $value;
        }

        return (float) $value;
    }

    /**
     * Filter result attributes to only include explicitly selected columns and appends.
     * Only applies filtering if $select was explicitly used by the client.
     *
     * @param array<int, string> $selectedColumns
     * @param array<int, string> $selectedAppends
     * @param array<int, string> $with
     * @param array<int, string> $withCount
     */
    private function filterResultAttributes(mixed $result, array $selectedColumns, array $selectedAppends, array $with = [], array $withCount = []): void
    {
        // Only filter if columns were explicitly selected or if appends were selected
        if ($selectedColumns === [] && $selectedAppends === []) {
            return;
        }

        $allowedAttributes = array_merge(
            $selectedColumns,
            $selectedAppends,
            $with,
            $this->resolveWithCountAliases($withCount)
        );

        if ($result instanceof Model) {
            $this->filterModelAttributes($result, $allowedAttributes);

            return;
        }

        if ($result instanceof AbstractPaginator) {
            $result->setCollection(
                $result->getCollection()->map(function (mixed $item) use ($allowedAttributes): mixed {
                    if ($item instanceof Model) {
                        $this->filterModelAttributes($item, $allowedAttributes);
                    }

                    return $item;
                })
            );

            return;
        }

        if ($result instanceof Collection) {
            $result->each(function (mixed $item) use ($allowedAttributes): void {
                if ($item instanceof Model) {
                    $this->filterModelAttributes($item, $allowedAttributes);
                }
            });

        }
    }

    /**
     * Filter a single model to only include specified attributes.
     *
     * @param array<int, string> $allowedAttributes
     */
    private function filterModelAttributes(Model $model, array $allowedAttributes): void
    {
        if ($allowedAttributes === []) {
            return;
        }

        // Get all current attributes including eager-loaded relations
        $allAttributes = array_keys($model->toArray());

        // Find attributes that should be hidden (not in allowed list)
        $attributesToHide = array_diff($allAttributes, $allowedAttributes);

        if ($attributesToHide !== []) {
            // Then hide only the unselected ones
            $model->makeHidden($attributesToHide);
        }
    }

    /**
     * @param array<int, string> $withCount
     * @return array<int, string>
     */
    private function resolveWithCountAliases(array $withCount): array
    {
        $aliases = [];

        foreach ($withCount as $relationCount) {
            if (! is_string($relationCount) || trim($relationCount) === '') {
                continue;
            }

            $segments = preg_split('/\s+as\s+/i', $relationCount);
            if (is_array($segments) && isset($segments[1]) && is_string($segments[1]) && trim($segments[1]) !== '') {
                $aliases[] = trim($segments[1]);

                continue;
            }

            $aliases[] = trim($relationCount);
        }

        return array_values(array_unique($aliases));
    }
}
