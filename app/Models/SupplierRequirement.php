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

class SupplierRequirement extends Model
{
   public static function getPrettyName($plural = false){ if($plural) { return __("Supplier requirements"); } else { return __("Supplier requirement"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if(config('ledningssystemet.disable_supplier'))
         return [];
      
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

      // Cannot update if this is a partner provided list
      static::updating(function ($model) {
         if((null != auth()->user()) && $model->int_supplier_category->partner)
            abort(400, __('This is a partner controlled object where modifications are not allowed'));
      });
      
      // Cannot delete if this is a partner provided list
      static::deleting(function ($model) {
         if((null != auth()->user()) && $model->int_supplier_category->partner)
            abort(400, __('This is a partner controlled object where modifications are not allowed'));
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
      'created_at',
      'updated_at',
      'supplier_category_id',
      'reassessment',
      'supplier_id',
      'evaluated_at',
      'supplier_supplier_requirement_id',
      'note',
      'satisfactory',
      'evaluated_by_name',   
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'reassessment',
      'supplier_category_id'
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
      return (__CLASS__)::where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->where('supplier_category_id', request()->input('supplier_category_id', 0))
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
         'name' => ['required', Rule::unique('supplier_requirements')->where(fn ($query) => $query->where('supplier_category_id', $this->supplier_category_id))->ignore($this->id)],
         'reassessment' => 'boolean',
         'supplier_category_id' => 'required|exists:supplier_categories,id',
      ];
   }  

   /**
    * Get the supplier category
    */
   public function int_supplier_category() : BelongsTo
   {
       return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
   }   
}
