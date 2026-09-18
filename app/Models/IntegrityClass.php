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

class IntegrityClass extends Model
{
      
   public static function getPrettyName($plural = false){ if($plural) { return __("Integrity classes"); } else { return __("Integrity class"); }}

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
         if(!$model->ordinal)
            $model->ordinal = 999;
         
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
      'description',
      'ordinal',
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
      'ordinal',
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
      $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('ordinal', 'desc');
         
      if(request()->input('hidechecked', 0) && (new (__CLASS__))->status)  { return $returnCollection->get()->filter(function($item) { return ($item->status['level'] != 'info'); }); }
         
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
         'name' => ['required', Rule::unique('integrity_classes')->ignore($this->id)],
         'ordinal' => 'required|numeric'
      ];
   }
   
   /**
    * Get the information types
    */
   public function int_information_types() : BelongsToMany
   {
       return $this->belongsToMany(InformationType::class);
   }   
   /**
    * Get the assets
    */
   public function int_assets() : BelongsToMany
   {
       return $this->belongsToMany(Asset::class);
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
      $afterobj = (null == $after) ? null : IntegrityClass::findOrFail($after);
    
      if(null == $afterobj)
         $this->ordinal = 999;
      else
         $this->ordinal = $afterobj->ordinal - 1;

      $this->save();
    
      // Re-order all objects, the slow way...
      $ordinal = 2;
      foreach(IntegrityClass::orderBy('ordinal')->get() as $obj)
      {
         $obj->ordinal = $ordinal;
         $obj->save();

         $ordinal += 2;
      }

      return [];
   } 
}

