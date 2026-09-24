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
use App\Models\ComplianceEvaluationRequirementFinding;
use App\Models\ActivityLog;
use App\Traits\HasNotifications;
use App\Models\Concerns\DefersRelationAttributeSync;
use Illuminate\Database\Eloquent\Casts\Attribute;



class ComplianceEvaluation extends Model
{
   use DefersRelationAttributeSync, HasNotifications;

   public static function getPrettyName($plural = false){ if($plural) { return __("Compliance evaluations"); } else { return __("Compliance evaluation"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      
      if(null != $department)
         return [];
            
      if($personalOnly)
         return [];
      
      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', ComplianceEvaluation::class))
         return [];
      
      $count = ComplianceEvaluation::whereNull('finished')->where('startdate', '<=', date("Y-m-d"))->count();
      if($count > 0)
         $retval[] = ['level' => 'warning', 'count' => $count, 'text' => __("Ongoing").' '.strtolower(ComplianceEvaluation::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/evaluations') : null];

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
         
         // Prevent setting archived unless evaluation is finished
         if($model->isDirty('archived'))
         {
            if(null == $model->finished)
               abort(400, __('You cannot archive an unfinished evaluation'));
         }
         else
         {
            if($model->isDirty('finished'))
            {
               // Re-open if not archived, otherwise prevent changing of finished state
               if(null != $model->archived)
                  abort(400, __('An archived evaluation cannot be re-opened'));
            }
            else {
               // Prevent updating of finished evaluations
               if ($model->getOriginal('finished'))
                  abort(400, __('A finished evaluation cannot be changed'));
            }
         }

         // Prevent finishing if every requirement is not evaluated
         if($model->finished && $model->int_compliance_evaluation_requirements()->where('evaluated', false)->count())
               abort(400, __('An evaluation cannot be finished until all requirements have been evaluated'));
      });
      
      static::saved(function ($model) {
         // Create findings if closing the evaluation
         if(!$model->getOriginal('finished') && $model->finished)
         {
            foreach($model->int_compliance_evaluation_requirements as $req)
            {
               foreach(ComplianceEvaluationRequirementFinding::where('compliance_evaluation_requirement_id', $req->id)->get() as $finding)
               {
                  $newFinding = new \App\Models\Finding();
                  $newFinding->name = $finding->name;
                  $newFinding->description = $finding->description;
                  $newFinding->department_id = $finding->department_id;
                  $newFinding->nonconformity = $finding->isnc;
                  $newFinding->compliance_evaluation_requirement_finding_id = $finding->id;
                  $newFinding->save();
                  
                  ActivityLog::addMessage(__("The finding was identified during compliance evaluation")." ".$model->name, $newFinding);
               }
            }         
         } 
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'requirement_sources',
      'statistics',
      'status',
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
      if($this->archived)
         return ['icon' => 'inventory_2', 'level' => 'info', 'text' => __('This evaluation is archived')];
      
      if($this->finished)
         return ['icon' => 'check', 'level' => 'info', 'text' => __('This evaluation is finished')];

      if(strtotime($this->startdate.' 00:00:00') <= time())
         return ['icon' => 'report', 'level' => 'warning', 'text' => __('This evaluation is ongoing')];

      return ['icon' => 'calendar_month', 'level' => 'info', 'text' => __('This evaluation is scheduled to start on').' '.date(__('Y-m-d'), strtotime($this->startdate))];
      
   }
   
   public function getRequirementsourcesAttribute()
   {
      if ($this->relationLoaded('int_requirement_sources')) {
         return $this->getRelation('int_requirement_sources')
            ->map(fn($obj) => ['id' => $obj->id, 'name' => $obj->name, 'reference' => $obj->reference, 'not_applicable_at' => $obj->not_applicable_at])
            ->all();
      }

      return Cache::rememberForever('ComplianceEvaluation.getRequirementsourcesAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_requirement_sources as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name, 'reference' => $obj->reference, 'not_applicable_at' => $obj->not_applicable_at);
         return $retval;
      });
   }
   
   public function setRequirementsourcesAttribute($value)
   {
      $this->syncRelationAttribute('requirementsources', fn ($model) => $model->int_requirement_sources()->sync($value));
   }
   
   public function getComplianceEvaluationRequirementsAttribute()
   {
      if ($this->relationLoaded('int_compliance_evaluation_requirements')) {
         return $this->getRelation('int_compliance_evaluation_requirements');
      }

      return Cache::rememberForever('ComplianceEvaluation.getComplianceEvaluationRequirementsAttribute.'.$this->id, function(){
         return $this->int_compliance_evaluation_requirements()->get();
      });
   }
   
   public function getStatisticsAttribute()
   {
       $precomputedKeys = [
          'stats_requirements_count',
          'stats_pass_count',
          'stats_fail_count',
          'stats_na_count',
          'stats_open_count',
       ];

       if (count(array_intersect($precomputedKeys, array_keys($this->attributes))) === count($precomputedKeys)) {
          return [
             'requirements' => intval($this->attributes['stats_requirements_count']),
             'pass' => intval($this->attributes['stats_pass_count']),
             'fail' => intval($this->attributes['stats_fail_count']),
             'na' => intval($this->attributes['stats_na_count']),
             'open' => intval($this->attributes['stats_open_count']),
          ];
       }

       return $this->getstats();
   }
   
    /**
     * Mutator for finished
     */
    protected function finished(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => (0 < intval($value) ? date("Y-m-d H:i:s") : null),
        );
    }   
   
    /**
     * Mutator for archived
     */
    protected function archived(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => ($value ? date("Y-m-d H:i:s") : null),
        );
    }   

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'startdate',
      'description',
      'participants',
      'summary',
      'finished',
      'archived',
      'created_at',
      'updated_at',
      'requirement_sources',
      'statistics',
      'status',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'participants',
      'startdate',
      'requirement_sources',
      'summary',
      'finished',
      'archived',
    ];



   /**
    * The public actions available
    */
   public $actions = [
      'finish',
      'archive',
      'generate',
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
      $today = date("Y-m-d");

      $returnCollection = (__CLASS__)::
         where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('startdate', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when(request()->has('hidearchived'), function (Builder $query){
               $query->whereNull('archived');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(request()->input('hidechecked', 0) && (new (__CLASS__))->status, function (Builder $query) use ($today) {
            // "Warning" status in this model means ongoing (started, unfinished and not archived).
            $query->whereNull('archived')->whereNull('finished')->where('startdate', '<=', $today);
         })
         ->with([
            'int_requirement_sources:id,name,reference,not_applicable_at',
            'int_compliance_evaluation_requirements' => function ($query) {
               $query
                  ->with('int_requirement:id,applicable')
                  ->withCount([
                     'int_compliance_evaluation_requirement_findings as nccount' => function ($findingQuery) {
                        $findingQuery->where('isnc', true);
                     },
                     'int_compliance_evaluation_requirement_findings as obscount' => function ($findingQuery) {
                        $findingQuery->where('isnc', false);
                     },
                  ]);
            },
         ])
         ->withCount([
            'int_compliance_evaluation_requirements as stats_requirements_count',
            'int_compliance_evaluation_requirements as stats_open_count' => function ($query) {
               $query->where('evaluated', false);
            },
            'int_compliance_evaluation_requirements as stats_na_count' => function ($query) {
               $query->where('evaluated', true)->where('applicable', false);
            },
            'int_compliance_evaluation_requirements as stats_fail_count' => function ($query) {
               $query
                  ->where('evaluated', true)
                  ->where('applicable', true)
                  ->whereHas('int_compliance_evaluation_requirement_findings', function ($findingQuery) {
                     $findingQuery->where('isnc', true);
                  });
            },
            'int_compliance_evaluation_requirements as stats_pass_count' => function ($query) {
               $query
                  ->where('evaluated', true)
                  ->where('applicable', true)
                  ->whereDoesntHave('int_compliance_evaluation_requirement_findings', function ($findingQuery) {
                     $findingQuery->where('isnc', true);
                  });
            },
         ])
         ->orderBy('finished')
         ->orderBy('archived')
         ->orderBy('startdate');
         
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
         'name' => ['required', Rule::unique('compliance_evaluations')->ignore($this->id)],
      ];
   }
   
   /**
     * Get requirement sources
     */
   public function int_requirement_sources() : BelongsToMany
   {
      return $this->belongsToMany(RequirementSource::class);
   }

   /**
     * Get compliance evaluation requirements
     */
   public function int_compliance_evaluation_requirements() : HasMany
   {
      return $this->hasMany(ComplianceEvaluationRequirement::class);
   }

   /**
    * Get compliance evaluation requirement sources
    */
   public function int_compliance_evaluation_requirement_sources() : HasMany
   {
      return $this->hasMany(ComplianceEvaluationRequirementSource::class);
   }

   /**
    * Get statistics
    */
   public function getstats()
   {
      $retval = array(
         'requirements' => 0,
         'pass' => 0,
         'fail' => 0,
         'na' => 0,
         'open' => 0,
      );

      $requirements = $this->relationLoaded('int_compliance_evaluation_requirements')
         ? $this->getRelation('int_compliance_evaluation_requirements')
         : $this->int_compliance_evaluation_requirements()->select(['id', 'evaluated', 'applicable'])->get();

      $requirementIds = $requirements->pluck('id')->all();
      $failedRequirementIds = [];

      if (!empty($requirementIds)) {
         $failedRequirementIds = ComplianceEvaluationRequirementFinding::query()
            ->whereIn('compliance_evaluation_requirement_id', $requirementIds)
            ->where('isnc', '1')
            ->groupBy('compliance_evaluation_requirement_id')
            ->pluck('compliance_evaluation_requirement_id')
            ->flip()
            ->all();
      }

      foreach($requirements as $req)
      {
         $retval['requirements']++;
         
         if($req->evaluated)
         {
            if($req->applicable)
            {
               if(array_key_exists($req->id, $failedRequirementIds))
                  $retval['fail']++;
               else
                  $retval['pass']++;
            }
            else
            {
               $retval['na']++;
            }
         }
         else
            $retval['open']++;
      }
      
      return $retval;
   }
   
   /**
    * Generate checklist
    */
   public function generate()
   {
      if(!request()->user()->can('update', $this))
         abort(403);
      
      // Ignore if already in this state
      if(null != $this->finished)
         throw new \App\Exceptions\SoftException(__('Cannot alter a finished checklist'));
      
      // Sync requirement sources
      $this->int_requirement_sources()->sync(request()->input('reqsources'));
      
      // Generate requirements
      $requirements = [];
      foreach($this->int_requirement_sources()->withPivot('id')->get() as $rs)
      {
         foreach($rs->int_requirements as $sourcereq)
            $requirements[$sourcereq->id] = [
               'compliance_evaluation_id' => $this->id,
               'requirement_id' => $sourcereq->id,
               'cers_id' => $rs->pivot->id,
               'name' => $sourcereq->name,
               'reference' => $sourcereq->reference,
               'description' => $sourcereq->description,
               'governance' => $sourcereq->governance,
               'created_at' => date("Y-m-d H:i:s"),
               'updated_at' => date("Y-m-d H:i:s"),
            ];
      }
      
      // Create any missing requirements
      foreach(array_keys($requirements) as $key)
      {
         // Check if already in list
         $existingreq = $this->int_compliance_evaluation_requirements()->where('requirement_id', $key)->first();
         
         // Create if new
         if(null == $existingreq)
         {
            $cer = new ComplianceEvaluationRequirement;
            foreach(array_keys($requirements[$key]) as $objkey)
               $cer->$objkey = $requirements[$key][$objkey];

            $cer->save();
         }
         else // Update
         {
            $existingreq->name = $requirements[$key]['name'];
            $existingreq->reference = $requirements[$key]['reference'];
            $existingreq->description = $requirements[$key]['description'];
            $existingreq->governance = $requirements[$key]['governance'];
            $existingreq->save();
         }
      }
   }


}


