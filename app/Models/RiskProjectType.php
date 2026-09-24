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
use App\Traits\HasTags;
use App\Traits\HasMessages;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Support\Facades\DB;

class RiskProjectType extends Model
{
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Risk project types"); } else { return __("Risk project type"); }}

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
      'status',
   ];
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'id',
      'name',
      'description',
   ];


   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];
   
   /**
    * The public actions available
    */
   public $actions = [
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
         'name' => 'required',
      ];
   }
   
   /**
     * Get the risk templates
     */
   public function int_risk_templates() : HasMany
   {
       return $this->hasMany(RiskProjectTypeRiskTemplate::class, 'risk_project_type_id');
   }   
}
