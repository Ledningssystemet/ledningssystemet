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


class ComplianceEvaluationRequirementFinding extends Model
{
      
   public static function getPrettyName($plural = false){ if($plural) { return __("Compliance evaluation requirement findings"); } else { return __("Compliance evaluation requirement finding"); }}

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
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
         
         // Prevent updating of finished evaluations
         if($model->int_compliance_evaluation_requirement->int_compliance_evaluation->finished)
            abort(400, __('A finished evaluation cannot be changed'));
      });

      // Prevent updating of finished evaluations
      static::deleting(function ($model) {
         if($model->int_compliance_evaluation_requirement->int_compliance_evaluation->finished)
            abort(400, __('A finished evaluation cannot be changed'));
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
      'compliance_evaluation_requirement_id',
      'department_id',
      'name',
      'description',
      'isnc',
      'created_at',
      'updated_at',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'compliance_evaluation_requirement_id',
      'name',
      'description',
      'department_id',
      'isnc',
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
      $returnCollection = (__CLASS__)::when(request()->input('compliance_evaluation_requirement_id', 0), function (Builder $query){
            $query->where('compliance_evaluation_requirement_id', request()->input('compliance_evaluation_requirement_id', 0));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
      ;
         
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
         'compliance_evaluation_requirement_id' => 'required|exists:App\Models\ComplianceEvaluationRequirement,id',
         'description' => 'required',
         'department_id' => 'required|exists:App\Models\Department,id',
         'isnc' => 'required|boolean',
      ];
   }
   
  /**
     * Get compliance evaluation requirement
     */
   public function int_compliance_evaluation_requirement() : BelongsTo
   {
      return $this->belongsTo(ComplianceEvaluationRequirement::class, 'compliance_evaluation_requirement_id');
   }

   public function int_department() : BelongsTo
   {
      return $this->belongsTo(Department::class, 'department_id');
   }   
}


