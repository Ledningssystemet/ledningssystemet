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
use App\Models\Concerns\DefersRelationAttributeSync;

use Illuminate\Support\Facades\DB;

class ProcessSustainabilityAspect extends Model
{
   use DefersRelationAttributeSync, HasTags, HasMessages;
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Sustainability aspects"); } else { return __("Sustainability aspect"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      $table = (new self())->getTable();

      // "without assessment" == status icon "help" == at least one required metric
      // for the selected sustainability aspect has no selected level for this PSA row.
      $count = self::query()
         ->whereExists(function ($q) use ($table) {
            $q->selectRaw('1')
               ->from('sustainability_aspect_sustainability_metric as sasm')
               ->leftJoin('process_sustainability_aspect_sustainability_metric as psasm', function ($join) use ($table) {
                  $join->on('psasm.sustainability_metric_id', '=', 'sasm.sustainability_metric_id')
                     ->whereColumn('psasm.process_sustainability_aspect_id', $table . '.id');
               })
               ->whereColumn('sasm.sustainability_aspect_id', $table . '.sustainability_aspect_id')
               ->whereNull('psasm.sustainability_metric_id');
         })
         ->count();

      if (!$personalOnly && $count) {
         $retval[] = [
            'level' => 'danger',
            'count' => $count,
            'text' => ProcessSustainabilityAspect::getPrettyName($count > 1) . ' ' . __("without assessment"),
            'url' => ((($user != null) && $user->can('index', get_called_class())) ||
               (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
               ? url()->query('/inventory/sustainabilityaspects')
               : null,
         ];
      }

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
      'status',
      'process',
      'sustainability_aspect',
      'objectives',
      'process_performance_metrics',
      'significant',
      'metric_sum',
      'sustainability_metrics',
      'tags',
      'messagecount',
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
      $significant = $this->getSignificantAttribute();
      if(null === $significant)
         return ['icon' => 'help', 'level' => 'danger', 'text' => __("Aspect has not been assessed")];
      
      
      if($significant)
         return ['icon' => 'error', 'level' => 'info', 'text' => __("This is a significant aspect")];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }
   
   public function getProcessAttribute()
   {
      return array('id' => $this->int_process->id, 'name' => $this->int_process->name);
   }
   
   public function getSustainabilityAspectAttribute()
   {
      return array('id' => $this->int_sustainability_aspect->id, 'name' => $this->int_sustainability_aspect->name);
   }
   
   public function getObjectivesAttribute()
   {
      $retval = [];
      foreach($this->int_objectives as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function setObjectivesAttribute($value)
   {
      $this->syncRelationAttribute('objectives', fn ($model) => $model->int_objectives()->sync($value));
   }   
   
   public function getProcessPerformanceMetricsAttribute()
   {
      $retval = [];
      foreach($this->int_process_performance_metrics as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function setProcessPerformanceMetricsAttribute($value)
   {
      $this->syncRelationAttribute('processperformancemetrics', fn ($model) => $model->int_process_performance_metrics()->sync($value));
   }   
   
   public function getMetricSumAttribute()
   {
      $metrics = $this->int_sustainability_metrics();
      if(0 == count($metrics))
         return 0;
      
      $retval = 1;
      
      foreach($metrics as $obj)
      {
         if(null == $obj['level'])
            return null;
         
         $retval = $retval * $obj['level']->multiplier;
      }
      
      return $retval;
   }
   
   public function getSignificantAttribute()
   {
      $metricSum = $this->getMetricSumAttribute();
      if(null === $metricSum)
         return null;
      
      return ($metricSum >= $this->int_sustainability_aspect->threshold);
   }
   
   public function getSustainabilityMetricsAttribute()
   {
      return $this->int_sustainability_metrics();
   }
   
   public function setSustainabilityMetricsAttribute($value)
   {
      $this->syncRelationAttribute('sustainabilitymetrics', function ($model) use ($value) {
         // Update metrics
         foreach($model->int_sustainability_metrics() as $metric)
         {
            // Set value
            if(array_key_exists($metric['id'], $value))
            {
               $update = false;

               // Check if value is null
               if(null == $metric['level'])
                  $update = true;

               // Check if value has changed, if so delete current set value
               if(
                  (null != $metric['level']) &&
                  ($metric['level']->sustainability_metric_level_id != $value[$metric['id']]))
               {
                  DB::table('process_sustainability_aspect_sustainability_metric')
                     ->where('process_sustainability_aspect_id', $model->id)
                     ->where('sustainability_metric_id', $metric['id'])
                     ->delete();
                  $update = true;
               }

               if($update)
               {
                  // Load metric value
                  $sml = SustainabilityMetricLevel::findOrFail($value[$metric['id']]);
                  if($sml->sustainability_metric_id != $metric['id'])
                     abort(400, 'Invalid metric level id');

                  DB::table('process_sustainability_aspect_sustainability_metric')->insert([
                     'process_sustainability_aspect_id' => $model->id,
                     'sustainability_metric_level_id' => $sml->id,
                     'sustainability_metric_id' => $sml->sustainability_metric_id,
                     'created_at' => date("Y-m-d H:i:s"),
                     'updated_at' => date("Y-m-d H:i:s")
                  ]);
               }
            }

            // Check if current value is not null and no value is set. If so, delete any settings
            if((null !== $metric['level']) && (!array_key_exists($metric['id'], $value)))
            {
               DB::table('process_sustainability_aspect_sustainability_metric')
                  ->where('process_sustainability_aspect_id', $model->id)
                  ->where('sustainability_metric_id', $metric['id'])
                  ->delete();
            }
         }
      });
   }   
   
   public function getTagsAttribute()
   {
      return $this->tags()->get();
   }
   
   public function getMessagecountAttribute()
   {
      return $this->messages()->count();
   }
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'impact_description',
      'monitoring_description',
      'governance_description',
      'process',
      'sustainability_aspect',
      'process_id',
      'sustainability_aspect_id',
      'created_at',
      'updated_at',
      'status',
      'objectives',
      'process_performance_metrics',
      'metric_sum',
      'significant',
      'sustainability_metrics',
      'tags',
      'messagecount',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'impact_description',
      'monitoring_description',
      'governance_description',
      'process_id',
      'sustainability_aspect_id',
      'objectives',
      'process_performance_metrics',
      'sustainability_metrics',
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
         'process_id' => 'required|exists:processes,id',
         'sustainability_aspect_id' => 'required|exists:sustainability_aspects,id',
      ];
   }  
   
   public function int_process() : BelongsTo
   {
      return $this->belongsTo(Process::class, 'process_id');
   }

   public function int_sustainability_aspect() : BelongsTo
   {
      return $this->belongsTo(SustainabilityAspect::class, 'sustainability_aspect_id');
   }
   
   /**
     * Get metrics
     */
   public function int_process_performance_metrics() : BelongsToMany
   {
     return $this->belongsToMany(ProcessPerformanceMetric::class);
   }

   /**
     * Get objectives
     */
   public function int_objectives() : BelongsToMany
   {
     return $this->belongsToMany(Objective::class);
   }
   
   /**
     * Get applicable metrics
     */
   public function int_sustainability_metrics()
   {
      $retval = [];
      
      // Get assessed metrics
      $assessed = [];
      foreach(DB::table('process_sustainability_aspect_sustainability_metric')->where('process_sustainability_aspect_id', $this->id)->leftJoin('sustainability_metric_levels', 'process_sustainability_aspect_sustainability_metric.sustainability_metric_level_id', '=', 'sustainability_metric_levels.id')->get() as $obj)
         $assessed[$obj->sustainability_metric_id] = $obj;
      
      // Get all metrics
      foreach($this->int_sustainability_aspect->int_sustainability_metrics as $obj)
      {
         $retval[] = array(
            'id' => $obj->id,
            'name' => $obj->name,
            'level' => array_key_exists($obj->id, $assessed) ? $assessed[$obj->id] : null,
         );
      }
      
      return $retval;
   }
}

