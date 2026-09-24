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
use App\Traits\HasNotifications;

class ObjectiveProcessPerformanceMetric extends Model
{
   use HasTags, HasMessages, HasNotifications;
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Objective process performance metrics"); } else { return __("Objective process performance metric"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      if(null != $department)
         return [];

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
         
         // Ensure not trying to change metric
         if($model->id && $model->isDirty('process_performance_metric_id'))
            abort(400, __("You may not change metric id for an already created objective"));
         
         // Validate target value
         if($model->objective_target_value < $model->int_process_performance_metric->minvalue)
            abort(400, __("Target value cannot be lower than the metrics minimum value"));

         if($model->objective_target_value > $model->int_process_performance_metric->maxvalue)
            abort(400, __("Target value cannot be higher than the metrics maximum value"));
         
         // Validate acceptablevalue value
         if($model->objective_acceptable_value < $model->int_process_performance_metric->minvalue)
            abort(400, __("Acceptable value cannot be lower than the metrics minimum value"));

         if($model->objective_acceptable_value > $model->int_process_performance_metric->maxvalue)
            abort(400, __("Acceptable value cannot be higher than the metrics maximum value"));
         
         if($model->int_process_performance_metric->biggerisbetter && ($model->objective_acceptable_value > $model->objective_target_value))
            abort(400, __("Acceptable value cannot be higher than the target value"));
         
         if(!$model->int_process_performance_metric->biggerisbetter && ($model->objective_acceptable_value < $model->objective_target_value))
            abort(400, __("Acceptable value cannot be lower than the target value"));
         
         if(!$model->int_process_performance_metric->quantitative)
            abort(400, __("A non-quantitative metric cannot be used for objectives"));
         
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'processes',
      'name',
      'unit',
      'targetvalue',
      'acceptablevalue',
      'latest_value',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getReportedByAttribute()
   {
      if(null == $this->reported_by_id)
         return null;
      return User::find($this->reported_by_id)->first()->name;
   }
   
   public function getTargetvalueAttribute()
   {
      return ((float)$this->objective_target_value)/(10 ** $this->int_process_performance_metric->precision);
   }
   
   public function setTargetvalueAttribute($value)
   {
      $this->objective_target_value = round(((float)$value) * (10 ** $this->int_process_performance_metric->precision));
      $this->precision = $this->int_process_performance_metric->precision;
   }
   
   public function getAcceptablevalueAttribute()
   {
      return ((float)$this->objective_acceptable_value)/(10 ** $this->int_process_performance_metric->precision);
   }
   
   public function setAcceptablevalueAttribute($value)
   {
      $this->objective_acceptable_value = round(((float)$value) * (10 ** $this->int_process_performance_metric->precision));
      $this->precision = $this->int_process_performance_metric->precision;
   }
   
   public function getProcessesAttribute()
   {
      return $this->int_process_performance_metric->processes;
   }

   public function getNameAttribute()
   {
      return $this->int_process_performance_metric->name;
   }

   public function getUnitAttribute()
   {
      return $this->int_process_performance_metric->unit;
   }
   
   public function getLatestValueAttribute()
   {
      $value = $this->int_process_performance_metric->int_last_report();
      if(null == $value)
         return null;
      return $this->int_process_performance_metric->int_last_report()->calculatedvalue;
   }
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'targetvalue',
      'acceptablevalue',
      'process_performance_metric_id',
      'updated_at',
      'objective_id',
      'processes',
      'latest_value',
      'name',
      'unit',
   ];


   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'targetvalue',
      'acceptablevalue',
      'process_performance_metric_id',
      'objective_id',
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
      $returnCollection = (__CLASS__)::when(request()->has('objective_id'), function (Builder $query) {
            $query->where('objective_id',request()->input('objective_id'));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('objective_id');

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
         'objective_id' => 'required|exists:objectives,id',
         'process_performance_metric_id' => 'required|exists:process_performance_metrics,id',
         'targetvalue' => 'required|numeric',
         'acceptablevalue' => 'required|numeric',
      ];
   }
   
   public function int_objective() : BelongsTo
   {
      return $this->belongsTo(Objective::class, 'objective_id');
   }

   public function int_process_performance_metric() : BelongsTo
   {
      return $this->belongsTo(ProcessPerformanceMetric::class, 'process_performance_metric_id');
   }

}
