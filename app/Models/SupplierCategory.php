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

class SupplierCategory extends Model
{
   public static function getPrettyName($plural = false){ if($plural) { return __("Supplier categories"); } else { return __("Supplier category"); }}

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
      
      static::updating(function ($model) {
      });
       
      static::deleting(function ($model) {
      });

      static::created(function ($model) {
         if(0 == intval(request()->input('require_assessment', 1)))
         {
            // Create new assessment
            $supplierids = Supplier::pluck('id');
            foreach($supplierids as $supplierid)
            {
               DB::table('supplier_supplier_category')->insert([
                  'supplier_id' => $supplierid,
                  'supplier_category_id' => $model->id,
                  'applicable' => 0,
                  'created_at' => date("Y-m-d H:i:s"),
                  'updated_at' => date("Y-m-d H:i:s"),
                  'updated_by_name' => auth()->user() ? auth()->user()->name : 'SYSTEM',
               ]);
            }
         }
      });
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'reassessment',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }


   public function getReassessmentAttribute()
   {
      $intervals = SupplierCategory::getIntervals();
      foreach(array_keys($intervals) as $objkey)
      {
         if($intervals[$objkey]['increment'] == $this->reassessment_interval)
            return $objkey;
      }
      
      return null;
   }

   public function setReassessmentAttribute($value)
   {
      $intervals = SupplierCategory::getIntervals();
      if(!array_key_exists($value, $intervals))
         abort(400, __("Invalid interval provided"));
      
      $this->reassessment_interval = $intervals[$value]['increment'];
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
      'reassessment',
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
   ];


   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];
   
   public static function getIntervals()
   {
      return [
         'never' => ['text' => __("Never"), 'increment' => null ],
         'monthly' => ['text' => __("Monthly"), 'increment' => "+1 MONTHS" ],
         'quarterly' => ['text' => __("Quarterly"), 'increment' => "+3 MONTHS" ],
         'annually' => ['text' => __("Annually"), 'increment' => "+1 YEARS" ],
         '2years' => ['text' => __("Bi-annually"), 'increment' => "+2 YEARS" ],
         '3years' => ['text' => __("Every third year"), 'increment' => "+3 YEARS" ],
      ];
   }
   
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
         'name' => ['required'],
      ];
   }  

   /**
    * Get the requirements
    */
   public function int_requirements() : HasMany
   {
      return $this->hasMany(SupplierRequirement::class, 'supplier_category_id');
   }   
   
   /**
    * Get the suppliers
    */
   public function int_suppliers() : BelongsToMany
   {
       return $this->belongsToMany(Supplier::class);
   }   
}

