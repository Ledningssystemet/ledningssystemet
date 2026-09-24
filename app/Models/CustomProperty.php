<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class CustomProperty extends Model
{
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Custom properties"); } else { return __("Custom property"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      return $retval;
   }

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public static function boot()
   {
      parent::boot();
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   public static function getContexts()
   {
      $retval = [];

      // List all files in the Models directory
      $files = glob(app_path('Models/*.php'));
      foreach($files as $file)
      {
         // Get the class name from the file
         $class = pathinfo($file, PATHINFO_FILENAME);

         // Check if the class exists and is a subclass of Model
         if(class_exists("App\\Models\\$class") && is_subclass_of("App\\Models\\$class", Model::class))
         {
            $model = new ("App\\Models\\$class");
            if(method_exists($model, 'bootHasCustomProperties'))
            {
               $retval[] = $model::class;
            }
         }
      }

      return $retval;
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'created_at',
      'updated_at',
      'context',
      'type',
      'options',
      'ordinal',
      'display_on_card',
      'user_editable',
      'value',
      'required',
   ];

   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'context',
      'type',
      'options',
      'ordinal',
      'display_on_card',
      'user_editable',
      'value',
      'required',
   ];

   /**
    * The public actions available
    */
   public $actions = [
      'reorder',
   ];

   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];
    
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::
         where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(request()->input('context', null), function (Builder $query) {
            $query->where('context', request()->input('context'));;
         })
         ->orderBy('ordinal');
         
      return $returnCollection->paginate();
   }
   
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => 'required',
         'description' => 'required',
         'context' => 'required',
         'type' => 'required',
         'display_on_card' => 'required|boolean',
         'user_editable' => 'required|boolean',
         'required' => 'required|boolean',
      ];
   }

   public function getJtableType()
   {
      switch($this->type)
      {
         case 'string':
            return 'string';

         case 'textarea':
            return 'textarea';

         case 'boolean':
         case 'user':
         case 'department':
         case 'supplier':
         case 'customer':
         case 'asset':
         case 'process':
            return 'select';

         default:
            throw new \Exception('The custom property of type '.$this->type.' does not have a corresponding jtable type');
      }
   }

   public function getJtableOptions()
   {
      switch($this->type)
      {
         case 'string':
         case 'textarea':
            return json_encode(null);

         case 'boolean':
            return json_encode([
               ['Value' => 0, 'DisplayText' => __('No')],
               ['Value' => 1, 'DisplayText' => __('Yes')],
            ]);

         case 'user':
            return json_encode(array_merge([['Value' => null, 'DisplayText' => __("None"), 'Disabled' => $this->required ]], User::orderBy('name')->get()->map(function($user) { return ['Value' => $user->id, 'DisplayText' => $user->name]; })->toArray()));
         case 'department':
            return json_encode(array_merge([['Value' => null, 'DisplayText' => __("None"), 'Disabled' => $this->required ]], Department::orderBy('name')->get()->map(function($user) { return ['Value' => $user->id, 'DisplayText' => $user->name]; })->toArray()));
         case 'supplier':
            return json_encode(array_merge([['Value' => null, 'DisplayText' => __("None"), 'Disabled' => $this->required ]], Supplier::orderBy('name')->get()->map(function($user) { return ['Value' => $user->id, 'DisplayText' => $user->name]; })->toArray()));
         case 'customer':
            return json_encode(array_merge([['Value' => null, 'DisplayText' => __("None"), 'Disabled' => $this->required ]], Customer::orderBy('name')->get()->map(function($user) { return ['Value' => $user->id, 'DisplayText' => $user->name]; })->toArray()));
         case 'asset':
            return json_encode(array_merge([['Value' => null, 'DisplayText' => __("None"), 'Disabled' => $this->required ]], Asset::orderBy('name')->get()->map(function($user) { return ['Value' => $user->id, 'DisplayText' => $user->name]; })->toArray()));
         case 'process':
            return json_encode(array_merge([['Value' => null, 'DisplayText' => __("None"), 'Disabled' => $this->required ]], Process::orderBy('name')->get()->map(function($user) { return ['Value' => $user->id, 'DisplayText' => $user->name]; })->toArray()));

         default:
            throw new \Exception('The custom property of type '.$this->type.' does not have corresponding jtable options');
      }

   }

   /**
    * Re-order the elements
    */
   public function reorder()
   {
      // Authorize action
      if(!request()->user()->can('update', $this))
         abort(403);

      // Get after-object
      $after = request()->input('after');
      $afterobj = (null == $after) ? null : CustomProperty::findOrFail($after);

      if(null == $afterobj)
         $this->ordinal = 0;
      else
         $this->ordinal = $afterobj->ordinal + 1;

      $this->save();

      // Re-order all objects, the slow way...
      $ordinal = 2;
      foreach(CustomProperty::orderBy('ordinal')->get() as $prop)
      {
         $prop->ordinal = $ordinal;
         $prop->save();

         $ordinal += 2;
      }

      return [];
   }


}
