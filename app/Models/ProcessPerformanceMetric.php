<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
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
use App\Models\Concerns\DefersRelationAttributeSync;
use Illuminate\Database\Eloquent\Casts\Attribute;
use stdClass;

class ProcessPerformanceMetric extends Model
{
   use DefersRelationAttributeSync, HasTags, HasMessages, HasNotifications, HasCustomProperties;
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Process performance metrics"); } else { return __("Process performance metric"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if (null != $department)
         return [];

      // Don't report if user cannot perform any changes anyway
      if ((null != $user) && $user->cannot('update', ProcessPerformanceMetric::class))
         return [];

      $retval = [];
      $table = (new self())->getTable();

      $url = ((($user != null) && $user->can('index', get_called_class())) ||
         (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
         ? url()->query('/measure/processperformancemetrics')
         : null;

      $scope = self::query()
         ->when($user, fn (Builder $q) => $q->where('responsible_user_id', $user->id));

      $withoutAssignment = self::whereNull('responsible_user_id')->count();
      if (!$personalOnly && $withoutAssignment) {
         $retval[] = [
            'level' => 'danger',
            'count' => $withoutAssignment,
            'text' => ProcessPerformanceMetric::getPrettyName($withoutAssignment > 1) . ' ' . __("without assignment"),
            'url' => $url,
         ];
      }

      // Missing/late report:
      // - no report exists
      // - OR report interval exists and latest report is overdue
      $withoutProperReport = (clone $scope)
         ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($table) {
            $q->whereNotExists(function ($sub) use ($table) {
               $sub->selectRaw('1')
                  ->from('process_performance_metric_reports as r')
                  ->whereColumn('r.process_performance_metric_id', $table . '.id');
            })->orWhere(function (\Illuminate\Database\Eloquent\Builder $late) use ($table) {
               $late->whereNotNull('increment')
                  ->whereExists(function ($sub) use ($table) {
                     $sub->selectRaw('1')
                        ->from('process_performance_metric_reports as lr')
                        ->whereColumn('lr.process_performance_metric_id', $table . '.id')
                        ->whereRaw(
                           'lr.reporting_date_at = (
                           SELECT MAX(r2.reporting_date_at)
                           FROM process_performance_metric_reports r2
                           WHERE r2.process_performance_metric_id = ' . $table . '.id
                        )'
                        )
                        ->where(function ($interval) use ($table) {
                           $interval->whereRaw($table . ".increment = '+1 WEEKS' AND DATE_ADD(lr.reporting_date_at, INTERVAL 1 WEEK) < NOW()")
                              ->orWhereRaw($table . ".increment = '+1 MONTHS' AND DATE_ADD(lr.reporting_date_at, INTERVAL 1 MONTH) < NOW()")
                              ->orWhereRaw($table . ".increment = '+3 MONTHS' AND DATE_ADD(lr.reporting_date_at, INTERVAL 3 MONTH) < NOW()")
                              ->orWhereRaw($table . ".increment = '+4 MONTHS' AND DATE_ADD(lr.reporting_date_at, INTERVAL 4 MONTH) < NOW()")
                              ->orWhereRaw($table . ".increment = '+6 MONTHS' AND DATE_ADD(lr.reporting_date_at, INTERVAL 6 MONTH) < NOW()")
                              ->orWhereRaw($table . ".increment = '+12 MONTHS' AND DATE_ADD(lr.reporting_date_at, INTERVAL 12 MONTH) < NOW()");
                        });
                  });
            });
         })
         ->count();

      if ($withoutProperReport > 0) {
         $retval[] = [
            'level' => 'danger',
            'count' => $withoutProperReport,
            'text' => ProcessPerformanceMetric::getPrettyName($withoutProperReport > 1) . ' ' . __("without properly reported value"),
            'url' => $url,
         ];
      }

// Alarm threshold breached on latest report
      $alarmingCount = (clone $scope)
         ->whereNotNull('alarm_threshold')
         ->where('quantitative', true)
         ->whereExists(function ($sub) use ($table) {
            $sub->selectRaw('1')
               ->from('process_performance_metric_reports as lr')
               ->whereColumn('lr.process_performance_metric_id', $table . '.id')
               ->whereRaw(
                  'lr.reporting_date_at = (
               SELECT MAX(r2.reporting_date_at)
               FROM process_performance_metric_reports r2
               WHERE r2.process_performance_metric_id = ' . $table . '.id
            )'
               )
               ->where(function ($q) use ($table) {
                  $q->where(function ($bigger) use ($table) {
                     $bigger->where($table . '.biggerisbetter', true)
                        ->whereRaw('(lr.value / POW(10, lr.reportedprecision)) <= ' . $table . '.alarm_threshold');
                  })->orWhere(function ($smaller) use ($table) {
                     $smaller->where($table . '.biggerisbetter', false)
                        ->whereRaw('(lr.value / POW(10, lr.reportedprecision)) >= ' . $table . '.alarm_threshold');
                  });
               });
         })
         ->count();

      if ($alarmingCount > 0) {
         $retval[] = [
            'level' => 'warning',
            'count' => $alarmingCount,
            'text' => ProcessPerformanceMetric::getPrettyName($alarmingCount > 1) . ' ' . __("breaching alarm threshold"),
            'url' => $url,
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

      static::saving(function ($model) {
         
         if(($model->id > 0) &&
            (($model->isDirty('quantitative')) ||
             ($model->isDirty('biggerisbetter'))))
            abort(400, __("The metric type cannot be changed after creation"));
         
         if($model->quantitative)
         {
            if($model->minvalue >= $model->maxvalue)
               abort(400, __("Min value must be less than max value"));

            if(null != $model->alarm_threshold) {
               if ($model->alarm_threshold >= $model->maxvalue)
                  abort(400, __("The alarm threshold must be lower than the max value"));

               if ($model->alarm_threshold <= $model->minvalue)
                  abort(400, __("The alarm threshold must be higher than the min value"));
            }
         }
         
         // Remove metric type
         unset($model->metric_type);
         
         // Convert value for saving
         unset($model->min);
         unset($model->max);
         
         
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'messagecount',
      'metric_type',
      'min',
      'max',
      'reportcount',
      'processes',
      'report_interval',
      'postprocessing_function',
      'postprocessing_function_parameter1',
      'tags',
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
      if(!$this->responsible_user_id)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("A responsible user has not been assigned")];

      $lastreport = $this->int_last_report();
      if((null == $lastreport) || ((null != $this->report_interval) && (strtotime($this->increment, strtotime($lastreport->reporting_date_at)) < time())))
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("Reporting is required")];

      // Calculate alarm threshold
      if((null != $lastreport) && $this->alarm_threshold && $this->quantitative)
      {
         if($this->biggerisbetter && ($lastreport->calculatedvalue <= $this->alarm_threshold))
            return ['icon' => 'warning', 'level' => 'warning', 'text' => __("Alarm threshold breached")];
         else if(!$this->biggerisbetter && ($lastreport->calculatedvalue >= $this->alarm_threshold))
            return ['icon' => 'warning', 'level' => 'warning', 'text' => __("Alarm threshold breached")];

      }

      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
      
   }
   
   public function getMessagecountAttribute()
   {
      return $this->messages()->count();
   }

   public function getMetricTypeAttribute()
   {
      if(!$this->quantitative)
         return 3;
      else if($this->biggerisbetter)
         return 1;
      else return 2;
   }
   
   public function setMetricTypeAttribute($value)
   {
      if(3 == $value)
         $this->quantitative = false;
      else if(1 == $value)
      {
         $this->quantitative = true;
         $this->biggerisbetter = true;
      }
      else
      {
         $this->quantitative = true;
         $this->biggerisbetter = false;
      }
   }
   
   public function getMinAttribute()
   {
      return ((float)$this->minvalue)/(10 ** $this->precision);
   }
   
   public function setMinAttribute($value)
   {
      $this->minvalue = intval(round(floatval($value) * (10 ** intval(request()->input('precision', $this->precision)))));
   }
   
   public function getMaxAttribute()
   {
      return ((float)$this->maxvalue)/(10 ** $this->precision);
   }
   
   public function setMaxAttribute($value)
   {
      $this->maxvalue = intval(round(floatval($value) * (10 ** intval(request()->input('precision', $this->precision)))));
   }
   
   public function getReportcountAttribute()
   {
      return $this->int_process_performance_metric_reports->count();
   }

   public function getProcessesAttribute()
   {
      $retval = [];
      foreach($this->int_processes as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
         
      return $retval;
   }
   
   public function setProcessesAttribute($value)
   {
      $this->syncRelationAttribute('processes', fn ($model) => $model->int_processes()->sync($value));
   }
   
   public static function getIntervals()
   {
         return array(
            0 => array('text' => __("No interval specified"), 'increment' => null),
            1 => array('text' => __("Weekly"), 'increment' => '+1 WEEKS'),
            2 => array('text' => __("Monthly"), 'increment' => '+1 MONTHS'),
            3 => array('text' => __("Quarterly"), 'increment' => '+3 MONTHS'),
            4 => array('text' => __("Every 4 months"), 'increment' => '+4 MONTHS'),
            5 => array('text' => __("Every 6 months"), 'increment' => '+6 MONTHS'),
            6 => array('text' => __("Yearly"), 'increment' => '+12 MONTHS'),
         );
   }
   
   public function getReportIntervalAttribute()
   {
      $intervals = ProcessPerformanceMetric::getIntervals();
      
      foreach(array_keys($intervals) as $objkey)
      {
         if($intervals[$objkey]['increment'] == $this->increment)
            return $objkey;
      }
      
      return 0;
   }
   
   public function setReportIntervalAttribute($value)
   {
      $intervals = ProcessPerformanceMetric::getIntervals();
      $this->increment = $intervals[$value]['increment'];
   }


   public static function getMathFunctions(){
      return array(
         'avg_ytd' => __("Average year to date for Current year -{x} year"),
         'avg_days' => __("Average {x} days back"),
         'avg_months' => __("Average {x} months back"),
         'sum_days' => __("Sum {x} days back"),
         'sum_months' => __("Sum {x} months back"),
      );
   }

   public function getPostprocessingFunctionAttribute()
   {
      $pparray = json_decode($this->postprocessing);
      if(null == $pparray)
         return null;

      if(!property_exists($pparray, 'fx') || !$pparray->fx)
         return null;

      return $pparray->fx;
   }

   public function setPostprocessingFunctionAttribute($value)
   {
      // Ensure this is a valid math function
      if((null != $value) && !array_key_exists($value, ProcessPerformanceMetric::getMathFunctions()))
         abort(400, __("Invalid postprocessing function"));

      $pparray = json_decode($this->postprocessing) ?? new StdClass();
      $pparray->fx = $value;
      $this->postprocessing = json_encode($pparray);
   }

   public function getPostprocessingFunctionParameter1Attribute()
   {
      $pparray = json_decode($this->postprocessing);
      if(null == $pparray)
         return 0;

      if(!property_exists($pparray, 'param1'))
         return 0;

      return $pparray->param1;
   }

   public function setPostprocessingFunctionParameter1Attribute($value)
   {
      $pparray = json_decode($this->postprocessing) ?? new StdClass();
      $pparray->param1 = max(0, intval($value));
      $this->postprocessing = json_encode($pparray);
   }

   public function getTagsAttribute()
   {
      return $this->tags()->get();
   }


   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'responsible_user_id',
      'created_at',
      'updated_at',
      'processes',
      'messagecount',
      'status',
      'min',
      'max',
      'unit',
      'precision',
      'metric_type',
      'reportcount',
      'report_interval',
      'postprocessing_function',
      'postprocessing_function_parameter1',
      'alarm_threshold',
      'tags',
   ];


   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'responsible_user_id',
      'min',
      'max',
      'unit',
      'precision',
      'metric_type',
      'processes',
      'report_interval',
      'postprocessing_function',
      'postprocessing_function_parameter1',
      'alarm_threshold'
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
      $returnCollection = (__CLASS__)::where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhereHas('tags', function (Builder $query) {
                     $query->where('name', 'LIKE', '%'.request()->input('search').'%');
                  });

            });
         })
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
         })
         ->when((1 == intval(request()->input('showmyonly', 0))), function (Builder $query) {
            $query->where('responsible_user_id',auth()->user()->id);
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when((0 < intval(request()->input('tag_id', 0))), function (Builder $query) {
            $query->whereHas('tags', function (Builder $query) {
               $query->where('tags.id', intval(request()->input('tag_id', 0)));
            });
         })
         ->when(0 < count(array_filter(array_keys(request()->all()), function($var){ return (0 === strpos($var,'customproperty_')); })), function(Builder $query){ (__CLASS__)::getIndexQuery($query); })
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
         'name' => [
            'required',
         ],
         'responsible_user_id' => 'nullable|exists:App\Models\User,id',
         'precision' => 'numeric|min:0|max:9',
      ];
   }
   
   /**
    * Get the processes associated with the object
    */
    public function int_processes() : BelongsToMany
    {
       return $this->belongsToMany(Process::class);
    }       

   /**
    * Get the most recent report
    */
   public function int_last_report()
   {
      return $this->int_process_performance_metric_reports()->orderBy('reporting_date_at', 'desc')->first();
   }

   /**
    * Get the reports associated
    */
    public function int_process_performance_metric_reports() : HasMany
    {
       return $this->hasMany(ProcessPerformanceMetricReport::class, 'process_performance_metric_id');
    }       

}
