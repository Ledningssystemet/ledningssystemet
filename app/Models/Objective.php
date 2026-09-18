<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
use App\Traits\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
use App\Traits\HasMessages;
use App\Traits\HasNotifications;

class Objective extends Model
{
   use HasMessages, HasNotifications, HasCustomProperties, HasTags;
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Objectives"); } else { return __("Objective"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if(config('ledningssystemet.disable_processes'))
         return [];
      
      $retval = [];

      if(null != $department)
         return [];

      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', Objective::class))
         return [];
      
      $count = Objective::whereNull('archived_at')->whereNull('responsible_user_id')->count();
      if(!$personalOnly && $count)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Objective::getPrettyName($count > 1).' '.__("without assignment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/measure/objectives') : null];

      $count = $user ? Objective::whereNull('archived_at')->where('due', '<', date("Y-m-d"))->where('responsible_user_id', $user->id)->count() : Objective::whereNull('archived_at')->where('due', '<', date("Y-m-d"))->count();
      if($count > 0)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Objective::getPrettyName($count > 1).' '.__("overdue"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/measure/objectives') : null];

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

         if($model->archived_at)
            abort(400, __("This objective is archived and cannot be modified"));
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'messagecount',
      'controlactionscount',
      'pendingcontrolactionscount',
      'metricscount',
      'lastreport',
      'unacceptable',
      'acceptable',
      'ontarget',
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
      if($this->archived_at)
         return ['icon' => 'inventory_2', 'level' => 'info', 'text' => __('This objective is archived')];

      if(!$this->responsible_user_id)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("A responsible user has not been assigned")];
      
      if(strtotime($this->due) < time())
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("This objective is overdue")];

      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }
   
   public function getMessagecountAttribute()
   {
      return $this->messages()->count();
   }

   public function getControlactionscountAttribute()
   {
      return $this->int_control_actions()->count();
   }

   public function getPendingcontrolactionscountAttribute()
   {
      return $this->int_control_actions()->whereNull('finished_at')->count();
   }
   
   public function getMetricscountAttribute()
   {
      return $this->int_objective_process_performance_metrics()->count();
   }

   public function getLastreportAttribute()
   {
      $retval = null;
      foreach($this->int_objective_process_performance_metrics as $metric)
      {
         $lastreport = $metric->int_process_performance_metric->int_last_report();
         
         if(null == $lastreport)
            return null;
         
         if(null == $retval)
            $retval = strtotime($lastreport->reporting_date_at);
         else if(strtotime($lastreport->reporting_date_at) > $retval)
            $retval = strtotime($lastreport->reporting_date_at);
      }
      
      return (null == $retval) ? null : date("Y-m-d", $retval);
   }

   public function getUnacceptableAttribute()
   {
      foreach($this->int_objective_process_performance_metrics as $metric)
      {
         $lastreport = $metric->int_process_performance_metric->int_last_report();
         if(null == $lastreport)
            return true;
         
         if($metric->int_process_performance_metric->biggerisbetter)
         {
            if($lastreport->calculatedvalue < $metric->acceptablevalue)
               return true;
         }
         else
         {
            if($lastreport->calculatedvalue > $metric->acceptablevalue)
               return true;
         }
      }
      
      return false;
   }

   public function getAcceptableAttribute()
   {
      return !$this->getUnacceptableAttribute();
   }

   public function getOntargetAttribute()
   {
      foreach($this->int_objective_process_performance_metrics as $metric)
      {
         $lastreport = $metric->int_process_performance_metric->int_last_report();
         if(null == $lastreport)
            return false;
         
         if($metric->int_process_performance_metric->biggerisbetter)
         {
            if($lastreport->calculatedvalue < $metric->targetvalue)
               return false;
         }
         else
         {
            if($lastreport->calculatedvalue > $metric->targetvalue)
               return false;
         }
      }
      
      return true;
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
      'due',
      'messagecount',
      'status',
      'metricscount',
      'controlactionscount',
      'pendingcontrolactionscount',
      'lastreport',
      'unacceptable',
      'acceptable',
      'ontarget',
      'archived_at',
      'action_plan',
      'department_id',
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
      'due',
      'action_plan',
      'department_id',
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
      'archive',
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
         ->when((0 < intval(request()->input('tag_id', 0))), function (Builder $query) {
            $query->whereHas('tags', function (Builder $query) {
               $query->where('tags.id', intval(request()->input('tag_id', 0)));
            });
         })
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
         })
         ->when(0 < intval(request()->input('department_id', 0)), function (Builder $query) {
            $query->where('department_id', intval(request()->input('department_id', 0)));
         })
         ->when((1 == intval(request()->input('showmyonly', 0))), function (Builder $query) {
            $query->where('responsible_user_id',auth()->user()->id);
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when((0 == intval(request()->input('showarchived', 0))), function (Builder $query) {
            $query->whereNull('archived_at');
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
         'department_id' => 'nullable|exists:App\Models\Department,id',
      ];
   }
   
   public function int_objective_process_performance_metrics() : HasMany
   {
      return $this->hasMany(ObjectiveProcessPerformanceMetric::class, 'objective_id');
   }

   /**
    * Get the control actions associated with the objective
    */
   public function int_control_actions() : BelongsToMany
   {
      return $this->belongsToMany(ControlAction::class, 'control_action_mappings', 'objective_id', 'control_action_id');
   }

   /**
    * Archive the objective
    */
   public function archive()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure current user is the risk owner
      if(auth()->user()->id != $this->responsible_user_id)
         abort(400, __('Only the responsible user can archive an objective'));;

      if(null != $this->archived_at)
         abort(400, __('The objective has already been archived'));

      DB::table('objectives')->where('id', $this->id)->update(['archived_at' => date("Y-m-d H:i:s")]);
   }

}
