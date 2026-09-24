<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class TableController extends Controller
{
   public function index($model)
   {
      // Derive classname
      $classname = "App\\Models\\" . $model;

      // Ensure class exist
      if (!class_exists($classname))
         abort(404);

      // Ensure index function exist
      if (!method_exists($classname, 'index'))
         abort(404);

      // Ensure user have correct access
      if (!Gate::allows('index', $classname)) {
         abort(403);
      }

      $requestedFieldSelection = self::getRequestedFieldSelection($classname);
      self::applyQueryableFieldSelection($requestedFieldSelection);

      // Perform index using query parameters for filtering/sorting/search/pagination
      $queryBuilder = QueryBuilder::for($classname::query())
         ->allowedFilters(...(
            method_exists($classname, 'getAllowedFilters')
               ? $classname::getAllowedFilters()
               : self::getDefaultAllowedFilters($classname)
         ))
         ->allowedSorts(...(
            method_exists($classname, 'getAllowedSorts')
               ? $classname::getAllowedSorts()
               : self::getDefaultAllowedSorts($classname)
         ))
         ->allowedFields(...(
            method_exists($classname, 'getAllowedFields')
               ? $classname::getAllowedFields()
               : self::getDefaultAllowedFields($classname)
         ))
         ->allowedIncludes(...(
            method_exists($classname, 'getAllowedIncludes')
               ? $classname::getAllowedIncludes()
               : self::getDefaultAllowedIncludes($classname)
         ));

      $retval = request()->filled('per_page')
         ? $queryBuilder->paginate((int) request()->query('per_page'))->appends(request()->query())
         : $queryBuilder->get();

      // Perform security validation to make sure we do not reveal unauthorized data by applying filtering
      $filteredRetval = $retval->reject(function ($item) {
         if (!Gate::allows('view', $item)) {
            // Since this shall not happen, we need to report it as a bug
            Log::critical(auth()->user()->name . ' tried to index ' . get_class($item) . ' but received an authorized item (id ' . $item->id . '). Query: ' . json_encode(request()->all()));
            return true;
         }

         return false;
      });

      if ('Illuminate\Pagination\LengthAwarePaginator' == $retval::class)
         $retval->setCollection($filteredRetval);
      else
         $retval = $filteredRetval;

      self::applyRequestedFieldSelection($retval, $requestedFieldSelection);

      // Return response
      return response()->json($retval);
   }

   protected static function getDefaultAllowedFilters(string $classname): array
   {
      $model = new $classname();
      $visibleFields = self::getVisibleFields($model);
      $allowedFilters = [];

      foreach ($visibleFields as $field) {
         if (self::isRelationPath($field)) {
            $allowedFilters[] = AllowedFilter::partial($field);
            continue;
         }

         if (!self::isQueryableColumn($model, $field)) {
            continue;
         }

         if (self::isBooleanField($model, $field) || self::isNumericField($model, $field)) {
            $allowedFilters[] = AllowedFilter::exact($field);
            continue;
         }

         if (self::isTextField($model, $field)) {
            $allowedFilters[] = AllowedFilter::partial($field);
         }
      }

      return $allowedFilters;
   }

   protected static function getDefaultAllowedSorts(string $classname): array
   {
      $model = new $classname();
      $visibleFields = self::getVisibleFields($model);
      $allowedSorts = [];

      foreach ($visibleFields as $field) {
         if (self::isRelationPath($field) || !self::isQueryableColumn($model, $field)) {
            continue;
         }

         if (self::isBooleanField($model, $field) || self::isNumericField($model, $field) || self::isTextField($model, $field)) {
            $allowedSorts[] = AllowedSort::field($field);
         }
      }

      return $allowedSorts;
   }

   protected static function getDefaultAllowedIncludes(string $classname): array
   {
      $model = new $classname();
      $allowedIncludes = [];

      foreach (get_class_methods($classname) as $method) {
         if (method_exists(Model::class, $method) || Str::startsWith($method, ['get', 'set', 'scope'])) {
            continue;
         }

         if (!self::canIncludeRelation($model, $method)) {
            continue;
         }

         $allowedIncludes[] = AllowedInclude::relationship($method);
      }

      return $allowedIncludes;
   }

   protected static function getDefaultAllowedFields(string $classname): array
   {
      $model = new $classname();
      $allowedFields = [];

      foreach (self::getVisibleFields($model) as $field) {
         if (self::isRelationPath($field)) {
           continue;
         }

         $allowedFields[] = $field;
      }

      return $allowedFields;
   }

   protected static function getVisibleFields(Model $model): array
   {
      return $model->getVisible();
   }

   protected static function canIncludeRelation(Model $model, string $method): bool
   {
      try {
         return $model->{$method}() instanceof Relation && Gate::allows('index', $model->{$method}()->getRelated()::class);
      } catch (\Throwable $e) {
         return false;
      }
   }

   protected static function isQueryableColumn(Model $model, string $field): bool
   {
      return in_array($field, self::getColumnListing($model), true);
   }

   protected static function isAccessorField(Model $model, string $field): bool
   {
      return method_exists($model, 'hasGetMutator') && $model->hasGetMutator($field);
   }

   protected static function isRelationPath(string $field): bool
   {
      return str_contains($field, '.');
   }

   protected static function isBooleanField(Model $model, string $field): bool
   {
      return self::matchesCast($model, $field, ['bool', 'boolean']);
   }

   protected static function isNumericField(Model $model, string $field): bool
   {
      return self::matchesCast($model, $field, ['int', 'integer', 'real', 'float', 'double', 'decimal']) || Str::endsWith($field, '_id') || $field === 'id';
   }

   protected static function isTextField(Model $model, string $field): bool
   {
      if (self::matchesCast($model, $field, ['array', 'json', 'object', 'collection', 'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'])) {
         return false;
      }

      return !self::isBooleanField($model, $field) && !self::isNumericField($model, $field);
   }

   protected static function matchesCast(Model $model, string $field, array $types): bool
   {
      $cast = $model->getCasts()[$field] ?? null;

      if (!is_string($cast)) {
         return false;
      }

      $normalizedCast = Str::before($cast, ':');

      return in_array($normalizedCast, $types, true);
   }

   protected static function getColumnListing(Model $model): array
   {
      static $columnsPerTable = [];

      $table = $model->getTable();

      if (!array_key_exists($table, $columnsPerTable)) {
         $columnsPerTable[$table] = Schema::getColumnListing($table);
      }

      return $columnsPerTable[$table];
   }

   protected static function getRequestedFieldSelection(string $classname): ?array
   {
      $requestedFields = request()->query('fields');

      if (!is_array($requestedFields)) {
         return null;
      }

      $table = (new $classname())->getTable();
      $modelFields = $requestedFields[$table] ?? $requestedFields['_'] ?? null;

      if (!is_string($modelFields) || trim($modelFields) === '') {
         return null;
      }

      $visibleFields = array_values(array_unique(array_filter(array_map(
         static fn ($field) => trim((string) $field),
         explode(',', $modelFields)
      ))));

      if ($visibleFields === []) {
         return null;
      }

      $appends = (new $classname())->getAppends();
      $queryableFields = array_values(array_diff($visibleFields, $appends));

      return [
         'table' => $table,
         'visible' => $visibleFields,
         'appends' => array_values(array_intersect(
            $visibleFields,
            $appends
         )),
         'queryable' => $queryableFields,
      ];
   }

   protected static function applyRequestedFieldSelection(LengthAwarePaginator|Collection $retval, ?array $requestedFieldSelection): void
   {
      if ($requestedFieldSelection === null) {
         return;
      }

      $visibleFields = $requestedFieldSelection['visible'] ?? [];
      $appends = $requestedFieldSelection['appends'] ?? [];

      $items = $retval instanceof LengthAwarePaginator ? $retval->getCollection() : $retval;

      $items->each(function (Model $model) use ($visibleFields, $appends) {
         $model->setVisible($visibleFields);
         $model->setAppends($appends);
      });
   }

   protected static function applyQueryableFieldSelection(?array $requestedFieldSelection): void
   {
      if ($requestedFieldSelection === null) {
         return;
      }

      $queryableFields = $requestedFieldSelection['queryable'] ?? [];
      $table = $requestedFieldSelection['table'] ?? null;

      if ($queryableFields === [] || !is_string($table) || $table === '') {
         return;
      }

      $sanitizedFields = request()->query('fields');
      if (!is_array($sanitizedFields)) {
         $sanitizedFields = [];
      }

      $sanitizedFields[$table] = implode(',', $queryableFields);
      $sanitizedFields['_'] = $sanitizedFields[$table];

      request()->merge(['fields' => $sanitizedFields]);
   }

   public function show($model, $id)
   {
      // Derive classname
      $classname = "App\\Models\\" . $model;

      // Ensure class exist
      if (!class_exists($classname))
         abort(404);

      // Ensure item exists
      $retval = $classname::findOrFail($id);

      // Ensure user have correct access
      if (!Gate::allows('view', $retval)) {
         abort(403);
      }

      // Return response
      return response()->json($retval);
   }

   public function create($model)
   {
      // Derive classname
      $classname = "App\\Models\\" . $model;

      // Ensure class exist
      if (!class_exists($classname))
         abort(404);

      // Ensure user have correct access
      if (!Gate::allows('create', $classname)) {
         abort(403);
      }

      DB::beginTransaction();

      // Create new item
      $newitem = $classname::create(request()->all());

      DB::commit();

      // Return response
      return response()->json($newitem);
   }

   public function update($model, $id)
   {
      // Derive classname
      $classname = "App\\Models\\" . $model;

      // Ensure class exist
      if (!class_exists($classname))
         abort(404);

      // Ensure item exists
      $retval = $classname::findOrFail($id);

      // Ensure user have correct access
      if (!Gate::allows('update', $retval)) {
         abort(403);
      }

      DB::beginTransaction();

      // Update item
      $retval->update(request()->all());

      DB::commit();

      // Return response
      return response()->json($retval);
   }

   public function delete($model, $id)
   {
      // Derive classname
      $classname = "App\\Models\\" . $model;

      // Ensure class exist
      if(!class_exists($classname))
         abort(404);

      // Ensure item exists
      $retval = $classname::findOrFail($id);

      // Ensure user have correct access
      if (!Gate::allows('delete', $retval)) {
         abort(403);
      }

      DB::beginTransaction();

      // Perform delete
      $retval->delete();

      DB::commit();

      // Return response
      return response()->json([]);
   }

   public function customAction($model, $id, $action)
   {
      // Derive classname
      $classname = "App\\Models\\".$model;

      // Ensure class exist
      if(!class_exists($classname))
         abort(404);

      // Ensure item exists
      $retval = $classname::findOrFail($id);

      // Ensure function exists
      if( !property_exists($retval, 'actions') ||
         !is_array($retval->actions) ||
         (false === array_search($action, $retval->actions)))
         abort(404);

      // Ensure user have at least view access. Note: The functions are responsible for authorization checks
      if (!Gate::allows('view', $retval)) {
         abort(403);
      }

      // Return response
      return response()->json($retval->$action());
   }
}
