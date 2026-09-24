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

class GhgCategory extends Model
{
       
   public static function getPrettyName($plural = false){ if($plural) { return __("GHG Categories"); } else { return __("GHG Category"); }}

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
      'description',
      'scope',
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
      'scope',
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
         'scope' => 'integer|min:1|max:3',
      ];
   }

   /**
    * Get conversion factors
    */
   public function int_ghg_conversion_factors()
   {
      return $this->hasMany(GhgConversionFactor::class);
   }
}
