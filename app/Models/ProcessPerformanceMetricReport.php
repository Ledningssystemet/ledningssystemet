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

class ProcessPerformanceMetricReport extends Model
{
   use HasTags, HasMessages, HasNotifications;
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Process performance metric reports"); } else { return __("Process performance metric report"); }}

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
         $model->reported_by_id = (null == request()->user()) ? null : request()->user()->id;
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'reported_by',
      'reportvalue',
      'calculatedvalue',
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
      return User::where('id', $this->reported_by_id)->first()->name;
   }
   
   public function getReportvalueAttribute()
   {
      return ((float)$this->value)/(10 ** $this->reportedprecision);
   }
   
   public function setReportvalueAttribute($value)
   {
      $this->reportedprecision = $this->int_process_performance_metric->precision;
      $this->value = round(((float)$value) * (10 ** $this->reportedprecision));
   }

   public function getCalculatedvalueAttribute()
   {
      $postproc = $this->int_process_performance_metric->postprocessing;
      if(null == $postproc)
         return $this->getReportvalueAttribute();

      $postprocfunc = json_decode($postproc);
      if(!$postprocfunc)
         return $this->getReportvalueAttribute();

      if(!property_exists($postprocfunc, 'fx') | !property_exists($postprocfunc, 'param1'))
         return $this->getReportvalueAttribute();

      if(!$postprocfunc->fx)
         return $this->getReportvalueAttribute();

      $paramval = intval($postprocfunc->param1);
      $startdate = null;
      $enddate = null;
      $func = null;
      switch($postprocfunc->fx)
      {
         case 'avg_ytd':
            $startdate = strtotime("JANUARY 1 -".$paramval." YEARS", strtotime($this->reporting_date_at));
            $enddate = strtotime("-".$paramval." YEAR", strtotime($this->reporting_date_at));
            $func = 'AVG';
            break;

         case 'avg_days':
            $startdate = strtotime("-".$paramval." DAYS", strtotime($this->reporting_date_at));
            $enddate = strtotime($this->reporting_date_at);
            $func = 'AVG';
            break;

         case 'avg_months':
            $startdate = strtotime("-".$paramval." MONTHS", strtotime($this->reporting_date_at));
            $enddate = strtotime($this->reporting_date_at);
            $func = 'AVG';
            break;

         case 'sum_days':
            $startdate = strtotime("-".$paramval." DAYS", strtotime($this->reporting_date_at));
            $enddate = strtotime($this->reporting_date_at);
            $func = 'SUM';
            break;

         case 'sum_months':
            $startdate = strtotime("-".$paramval." MONTHS", strtotime($this->reporting_date_at));
            $enddate = strtotime($this->reporting_date_at);
            $func = 'SUM';
            break;
         default:
            abort(400, __("Invalid postprocessing function"));
      }

      $startdate = date("Y-m-d", $startdate).' 00:00:00';
      $enddate = date("Y-m-d", $enddate).' 23:59:59';

      // Perform calculation
      $val = ProcessPerformanceMetricReport
         ::where('reporting_date_at', '>=', $startdate)
         ->where('reporting_date_at', '<=', $enddate)
         ->where('process_performance_metric_id', $this->process_performance_metric_id)
         ->select(\Illuminate\Support\Facades\DB::raw($func.'(value/POW(10, reportedprecision)) as calcvalue'))
         ->value('calcvalue');

      // Perform precision correction
      $precision = $this->int_process_performance_metric->precision ?? 0;
      return round($val, $precision);
   }


   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'reported_by',
      'reportvalue',
      'updated_at',
      'reporting_date_at',
      'comment',
      'process_performance_metric_id',
      'calculatedvalue',
   ];


   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'reported_by_id',
      'reportvalue',
      'updated_at',
      'reporting_date_at',
      'comment',
      'process_performance_metric_id',
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
      $returnCollection = (__CLASS__)::when(request()->has('process_performance_metric_id'), function (Builder $query) {
            $query->where('process_performance_metric_id',request()->input('process_performance_metric_id'));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('reporting_date_at', 'desc');

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
         'reported_by_id' => 'nullable|exists:App\Models\User,id',
         'process_performance_metric_id' => 'required|exists:process_performance_metrics,id',
         
      ];
   }
   
   public function int_process_performance_metric() : BelongsTo
   {
      return $this->belongsTo(ProcessPerformanceMetric::class, 'process_performance_metric_id');
   }
   
}
