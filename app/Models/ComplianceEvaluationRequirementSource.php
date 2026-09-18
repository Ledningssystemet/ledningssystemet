<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
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

class ComplianceEvaluationRequirementSource extends Model
{
   public static function getPrettyName($plural = false){ if($plural) { return __("Compliance evaluation requirement source"); } else { return __("Compliance evaluation requirement sources"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      return $retval;
   }
   
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'compliance_evaluation_requirement_source';

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public static function boot()
   {
      parent::boot();

      // Prevent updating of finished evaluations
      static::updating(function ($model) {
         if($model->int_compliance_evaluation->finished)
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
      'compliance_evaluation_id',
      'requirement_source_id',
      'note',
      'created_at',
      'updated_at',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'note',
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
      return (__CLASS__)
         ::where('compliance_evaluation_id', request()->input('compliance_evaluation_id', 0))
         ->when(0 < request()->input('requirement_source_id', 0), function (Builder $query){
               $query->where('requirements.requirement_source_id', request()->input('requirement_source_id', 0));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
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
      ];
   }
   
   /**
     * Get compliance evaluation
     */
   public function int_compliance_evaluation() : BelongsTo
   {
      return $this->belongsTo(ComplianceEvaluation::class, 'compliance_evaluation_id');
   }

   /**
     * Get requirement
     */
   public function int_requirement_source() : BelongsTo
   {
      return $this->belongsTo(RequirementSource::class, 'requirement_source_id');
   }
}


