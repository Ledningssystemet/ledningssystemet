<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;


class LegacyTableController extends Controller
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

      // Perform index
      $retval = $classname::index(request()->user());

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

      // Return response
      return response()->json($retval);
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

