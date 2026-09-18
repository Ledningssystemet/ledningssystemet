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

class GhgConversionFactor extends Model
{
       
   public static function getPrettyName($plural = false){ if($plural) { return __("GHG Conversion factors"); } else { return __("GHG Conversion factor"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      return [];
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
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });

      static::deleting(function ($model) {
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
      'ghg_category_id',
      'description',
      'activity_sourceunit',
      'activity_factor',
      'activity_datasource_name',
      'activity_datasource_url',
      'spend_sourceunit',
      'spend_factor',
      'spend_datasource_name',
      'spend_datasource_url',
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
      'description',
      'ghg_category_id',
      'activity_sourceunit',
      'activity_factor',
      'activity_datasource_name',
      'activity_datasource_url',
      'spend_sourceunit',
      'spend_factor',
      'spend_datasource_name',
      'spend_datasource_url',
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
      $returnCollection = (__CLASS__)::where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when(0 < intval(request()->input('ghg_category_id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.ghg_category_id', intval(request()->input('ghg_category_id', 0)));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('name');

     return $returnCollection->get();
   }
   
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => 'string|required',
         'description' => 'string|nullable',
         'ghg_category_id' => 'required|exists:App\Models\GhgCategory,id',
         'activity_sourceunit' => 'sometimes|string|nullable',
         'activity_factor' => 'sometimes|numeric|nullable',
         'activity_datasource_name' => 'sometimes|string|nullable',
         'activity_datasource_url' => 'sometimes|url|nullable',
         'spend_sourceunit' => 'sometimes|string|nullable',
         'spend_factor' => 'sometimes|numeric|nullable',
         'spend_datasource_name' => 'sometimes|string|nullable',
         'spend_datasource_url' => 'sometimes|url|nullable',
      ];
   }

   /**
    * Get the GHG category
    */
   public function int_ghg_category() : BelongsTo
   {
      return $this->belongsTo(GhgCategory::class, 'ghg_category_id');
   }
}
