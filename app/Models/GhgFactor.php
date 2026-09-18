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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GhgFactor extends Model
{
       
   public static function getPrettyName($plural = false){ if($plural) { return __("GHG factors"); } else { return __("GHG factor"); }}

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
      'emission_co2e_kg',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   public function getEmissionCo2eKgAttribute()
   {
      $conversionFactor = GhgConversionFactor::find($this->ghg_conversion_factor_id);
      if(null == $conversionFactor)
         return 0;

      $emissionRate = $this->is_activity_based
         ? $conversionFactor->activity_factor
         : $conversionFactor->spend_factor;
      if(null == $emissionRate)
         return 0;

      $sourceValue = $this->getEmissionSourceValue();
      if(null == $sourceValue)
         return 0;

      return ((float)$sourceValue) * ((float)$emissionRate);
   }

   private function getEmissionSourceValue()
   {
      if($this->process_performance_metrics_id)
      {
         $query = ProcessPerformanceMetricReport::query()
            ->where('process_performance_metric_id', $this->process_performance_metrics_id);
         $dateColumn = 'reporting_date_at';

         $this->applyRequestDateFilter($query, $dateColumn);
         return $this->calculateValueFromReports($query, $dateColumn, 'value / POW(10, reportedprecision)');
      }

      $query = GhgFactorReport::query()
         ->where('ghg_factor_id', $this->id);
      $dateColumn = 'valuedate';

      $this->applyRequestDateFilter($query, $dateColumn);
      return $this->calculateValueFromReports($query, $dateColumn, 'value');
   }

   private function applyRequestDateFilter(Builder $query, $dateColumn)
   {
      if(request()->filled('from'))
         $query->whereDate($dateColumn, '>=', request()->input('from'));

      if(request()->filled('to'))
         $query->whereDate($dateColumn, '<=', request()->input('to'));
   }

   private function calculateValueFromReports(Builder $query, $dateColumn, $valueExpression)
   {
      $postprocessingFunction = $this->normalizePostprocessingFunction();

      if('latest' == $postprocessingFunction)
      {
         $latest = (clone $query)
            ->orderBy($dateColumn, 'desc')
            ->orderBy('id', 'desc')
            ->select(DB::raw($valueExpression.' AS calcvalue'))
            ->first();

         return null == $latest ? null : ((float)$latest->calcvalue);
      }

      if('sum' == $postprocessingFunction)
      {
         $sum = (clone $query)
            ->select(DB::raw('SUM('.$valueExpression.') AS calcvalue'))
            ->value('calcvalue');

         return null == $sum ? 0 : ((float)$sum);
      }

      $average = (clone $query)
         ->select(DB::raw('AVG('.$valueExpression.') AS calcvalue'))
         ->value('calcvalue');

      return null == $average ? 0 : ((float)$average);
   }

   private function normalizePostprocessingFunction()
   {
      $function = strtolower(trim((string)$this->postprocessing_function));

      if(('' === $function) || ('latest' === $function))
         return 'latest';

      if(in_array($function, ['average', 'avg', 'avg_days', 'avg_months', 'avg_ytd']))
         return 'average';

      if(in_array($function, ['sum', 'sum_days', 'sum_months']))
         return 'sum';

      return 'latest';
   }

      /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'ghg_conversion_factor_id',
      'description',
      'is_activity_based',
      'process_performance_metrics_id',
      'postprocessing_function',
      'responsible_user_id',
      'created_at',
      'updated_at',
      'emission_co2e_kg',
   ];


   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'ghg_conversion_factor_id',
      'description',
      'is_activity_based',
      'process_performance_metrics_id',
      'postprocessing_function',
      'responsible_user_id',
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
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('name');

     return $returnCollection->get();
   }
   
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => 'string|required',
         'ghg_conversion_factor_id' => 'required|exists:ghg_conversion_factors,id',
         'description' => 'string|nullable',
         'is_activity_based' => 'boolean|required',
         'process_performance_metrics_id' => 'nullable|exists:process_performance_metrics,id',
         'postprocessing_function' => ['nullable', 'string', Rule::in(['latest', 'sum', 'average'])],
         'responsible_user_id' => 'nullable|exists:users,id',
      ];
   }
}
