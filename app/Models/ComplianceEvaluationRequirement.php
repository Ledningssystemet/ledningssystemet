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

class ComplianceEvaluationRequirement extends Model
{
      
   public static function getPrettyName($plural = false){ if($plural) { return __("Compliance evaluation requirements"); } else { return __("Compliance evaluation requirement"); }}

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
    protected $table = 'compliance_evaluation_requirement';

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
      'controls',
      'nccount',
      'obscount',
      'status',
      'requirement_applicable',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getStatusAttribute()
   {
      if($this->getNccountAttribute() > 0 || $this->getObscountAttribute() > 0)
         return ['icon' => 'report', 'level' => 'info', 'text' => ''];
      
      if(!$this->evaluated)
         return ['icon' => 'warning', 'level' => 'warning', 'text' => ''];
      
      if(!$this->applicable)
         return ['icon' => 'close', 'level' => 'warning', 'text' => ''];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
      
   }
   
   public function getControlsAttribute()
   {
      return Cache::rememberForever('ComplianceEvaluationRequirement.getControlsAttribute.'.$this->id, function(){
         if($this->int_requirement)
            return $this->int_requirement->int_controls()->get();
         else
            return [];
      });
   }

   public function getNccountAttribute()
   {
        if (array_key_exists('nccount', $this->attributes)) {
            return intval($this->attributes['nccount']);
        }

        if ($this->relationLoaded('int_compliance_evaluation_requirement_findings')) {
            return $this->getRelation('int_compliance_evaluation_requirement_findings')
               ->where('isnc', true)
               ->count();
        }

       return $this->int_compliance_evaluation_requirement_findings()->where('isnc', true)->count();
   }
   
   public function getObscountAttribute()
   {
        if (array_key_exists('obscount', $this->attributes)) {
            return intval($this->attributes['obscount']);
        }

        if ($this->relationLoaded('int_compliance_evaluation_requirement_findings')) {
            return $this->getRelation('int_compliance_evaluation_requirement_findings')
               ->where('isnc', false)
               ->count();
        }

       return $this->int_compliance_evaluation_requirement_findings()->where('isnc', false)->count();
   }
   
   public function getRequirementApplicableAttribute()
   {
      if ($this->relationLoaded('int_requirement')) {
         return $this->getRelation('int_requirement')?->applicable;
      }

      return $this->int_requirement->applicable;
   }
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'compliance_evaluation_id',
      'requirement_id',
      'cers_id',
      'name',
      'reference',
      'description',
      'governance',
      'note',
      'evaluated',
      'applicable',
      'created_at',
      'updated_at',
      'controls',
      'nccount',
      'obscount',
      'status',
      'requirement_applicable',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'evaluated',
      'applicable',
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
      return (__CLASS__)::join('requirements', 'requirements.id', '=', 'requirement_id')
         ->where('compliance_evaluation_id', request()->input('compliance_evaluation_id', 0))
         ->where(function (Builder $query) {
            $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('compliance_evaluation_requirement.name', 'LIKE', '%'.request()->input('search').'%')
                     ->orWhere('compliance_evaluation_requirement.reference', 'LIKE', '%'.request()->input('search').'%')
                     ->orWhere('compliance_evaluation_requirement.note', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when("1" == request()->input('hideevaluated', 0), function (Builder $query){
               $query->where('evaluated',false);
         })
         ->when(0 < request()->input('requirement_source_id', 0), function (Builder $query){
               $query->where('requirements.requirement_source_id', request()->input('requirement_source_id', 0));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('requirements.requirement_source_id')
         ->orderBy('requirements.ordinal')
         ->orderBy('requirements.id')
         ->paginate(null, ['compliance_evaluation_requirement.*']);
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
     * Get compliance evaluations
     */
   public function int_compliance_evaluation_requirement_findings() : HasMany
   {
      return $this->hasMany(ComplianceEvaluationRequirementFinding::class);
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
   public function int_requirement() : BelongsTo
   {
      return $this->belongsTo(Requirement::class, 'requirement_id');
   }
}


