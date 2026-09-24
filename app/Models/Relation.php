<?php

namespace App\Models;

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

class Relation extends Model
{
      public static function getPrettyName($plural = false){ if($plural) { return __("Relations"); } else { return __("Relation"); }}

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

      static::saving(function ($model) {
         if($model->id == null) {
            // Set model connection
            $classname = 'App\\Models\\' . $model->relation_type;
            if (!class_exists($classname))
               abort(404);

            $model->relation_type = $classname;
            $model->relation_id = request()->input('relation_id', 0);
         }

         $obj = $model->relation_type::find($model->relation_id);
         if(null == $obj)
            abort(400, __("The relation object could not be found"));

         if(request()->user()->cannot('view', $obj))
            abort(403);

         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });
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

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'email',
      'phone',
      'description',
      'relation_id',
      'relation_type',
      'created_at',
      'updated_at',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'email',
      'phone',
      'description',
      'relation_type',
      'relation_id',
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
      $classname = '\\App\\Models\\'.request()->input('relation_type', '');
      if(!class_exists($classname))
         abort(404);
      
      $obj = $classname::findOrFail(request()->input('relation_id', 0));
      
      if(request()->user()->cannot('index', $obj))
         abort(403);

      $returnCollection = (__CLASS__)::where('relation_type', $obj::class)
         ->where('relation_id', $obj->id)
         ->orderBy('updated_at', 'desc');

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
         'email' => 'required|email',
         'phone' => 'nullable|string',
         'relation_type' => 'required|string',
         'relation_id' => 'required|integer',
      ];
   }

}
