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

use Illuminate\Support\Facades\DB;
use App\Models\Concerns\DefersRelationAttributeSync;

class SustainabilityAspect extends Model
{
   use DefersRelationAttributeSync;

   public static function getPrettyName($plural = false){ if($plural) { return __("Sustainability aspects"); } else { return __("Sustainability aspect"); }}

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
      'sustainability_metrics',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }


   public function getSustainabilityMetricsAttribute()
   {
      $retval = [];
      foreach($this->int_sustainability_metrics as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function setSustainabilityMetricsAttribute($value)
   {
      $this->syncRelationAttribute('sustainabilitymetrics', fn ($model) => $model->int_sustainability_metrics()->sync($value));
   }   
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'threshold',
      'sustainability_metrics',
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
      'sustainability_metrics',
      'threshold',
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
      return (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('name')
         ->get();
   }

   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => ['required', Rule::unique('sustainability_aspects')->ignore($this->id)],
         'threshold' => 'sometimes|numeric',
      ];
   }  
 
   /**
    * Get the metrics
    */
   public function int_sustainability_metrics() : BelongsToMany
   {
      return $this->belongsToMany(SustainabilityMetric::class);
   }   
}

