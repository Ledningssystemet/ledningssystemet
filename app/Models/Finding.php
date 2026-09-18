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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\HasTags;
use App\Traits\HasMessages;
use App\Traits\HasNotifications;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Finding extends Model
{
   use HasMessages, HasNotifications, HasTags, HasCustomProperties;

   public static function getPrettyName($plural = false){ if($plural) { return __("Findings"); } else { return __("Finding"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if(config('ledningssystemet.disable_finding'))
         return [];

      $retval = [];
      
      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', Finding::class))
         return [];

      $departments = null;
      if($department)
         $departments = [$department->id];
      else if($user)
         $departments = $user->int_departments()->pluck('departments.id');
      
      $count = (null == $departments) ? Finding::whereNull('finished_at')->where('nonconformity', true)->count() : Finding::whereNull('finished_at')->where('nonconformity', true)->whereIn('department_id', $departments)->count();
      if($count)
         $retval[] = ['level' => (null == $user) ? 'warning' : 'danger', 'count' => $count, 'text' => __("Non-conformitites").' '.__("pending assessment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/findings') : null];
      
      $count = (null == $departments) ? Finding::whereNull('finished_at')->where('nonconformity', false)->count() : Finding::whereNull('finished_at')->where('nonconformity', false)->whereIn('department_id', $departments)->count();
      if($count)
         $retval[] = ['level' => (null == $user) ? 'info' : 'warning', 'count' => $count, 'text' => __("Observation").' '.__("pending assessment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/findings') : null];

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
         
         // Ensure valid context
         if($model->context_type)
         {
            if(null == $model->context_type::find($model->context_id))
               abort(400, __("Invalid context"));
         }
         else if($model->context_id)
         {
            abort(400, __("You may not provide a context id without context"));  
         }
         
         // Prevent changing a closed finding
         if(null != $model->getOriginal('finished_at'))
            abort(403, __("You may not change a closed finding"));
          
         if($model->finished_at)
         {
            // Ensure that all mandatory fields are provided
            if(null == $model->immediateaction)
               abort(400, __('There must be a description of immediate actions before a non-conformity can be marked as finished'));
            
            // Ensure a non-conformity is assessed
            if($model->nonconformity)
            {
               if(null == $model->rootcause)
                  abort(400,__('There must be a root cause analysis before a non-conformity can be marked as finished'));
                  
               if(null == $model->preventativeaction)
                  abort(400, __('There must be a description of preventative actions before a non-conformity can be marked as finished'));
            }
         }
      });
static::creating(function ($model) {
                  $model->created_by = auth()->user()->id;
      });
      
      // Prevent deletion if user did not wrote it by themselves and that it was not part of a compliance evaluation
      static::deleting(function ($model) {
         if((null != $model->compliance_evaluation_requirement_finding_id) ||
            ($model->created_by != auth()->user()->id))
            abort(400, __("You cannot delete a finding that you have not written yourself or that is part of a compliance evaluation"));
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'messagecount',
      'context',
      'created_at_pretty',
      'status',
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
      if($this->finished_at)
         return ['icon' => 'check', 'level' => 'info', 'text' => ''];
      
      if($this->nonconformity)
         return ['icon' => 'report', 'level' => 'danger', 'text' => __("This non-conformity has not been assessed")];
         
      return ['icon' => 'report', 'level' => 'warning', 'text' => __("This observation has not been assessed")];
   }
   
   public function getCreatedAtPrettyAttribute()
   {
      return date("Y-m-d H:i", strtotime($this->created_at));
   }

   public function getMessagecountAttribute()
   {
      if (array_key_exists('messages_count', $this->attributes)) {
         return intval($this->attributes['messages_count']);
      }

      return $this->messages()->count();
   }

   public function getContextAttribute()
   {
      if((null != $this->context_type) && (null != $this->context_id))
      {
         $contextParts = explode('\\',$this->context_type);
         return (end($contextParts).'_'.$this->context_id);
      }
      else
         return null;
   }
      
   public function setContextAttribute($value)
   {
      if(null == $value)
      {
         $this->context_type = null;
         $this->context_id = null;
      }
      else
      {
         // Validate class
         $contextParts=explode('_', $value);
         if(null == $contextParts)
         if(2 !== count($contextParts))
            abort(400, __("Invalid context"));
         
         $classname = 'App\\Models\\'.$contextParts[0];
         if(!class_exists($classname))
            abort(400, __("Invalid context type"));
         
         switch($classname)
         {
            case 'App\\Models\\Process':
            case 'App\\Models\\Asset':
            case 'App\\Models\\Supplier':
               break;
            default:
               abort(400, __("Invalid context type"));
         }
         
         $this->context_type = $classname;
         
         $classInstance = $this->context_type::findOrFail($contextParts[1]);
         $this->context_id = $classInstance->id;
      }
   }

   public function getTagsAttribute()
   {
      if ($this->relationLoaded('tags')) {
         return $this->getRelation('tags');
      }

      return $this->tags()->get();
   }
   
   /**
    * Finished_at set mutator
    */
   protected function finishedAt(): Attribute
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
      'description',
      'department_id',
      'finished_at',
      'nonconformity',
      'consequence',
      'rootcause',
      'immediateaction',
      'preventativeaction',
      'compliance_evaluation_requirement_finding_id',
      'created_at',
      'updated_at',
      'context_type',
      'context_id',
      'created_by',
      'messagecount',
      'context',
      'created_at_pretty',
      'status',
      'tags',
      'distribution_analysis',
      'estimated_cost',
   ];

   
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'department_id',
      'description',
      'consequence',
      'rootcause',
      'immediateaction',
      'preventativeaction',
      'nonconformity',
      'context',
      'finished_at',
      'distribution_analysis',
      'estimated_cost',
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
      'reopen',
   ];

   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $table = (new self())->getTable();

      $returnCollection = self::query()
         ->where(function (Builder $query) {
            $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('id', str_ireplace('FINDING-', '', request()->input('search')))
                  ->orWhereHas('tags', function (Builder $query) {
                     $query->where('name', 'LIKE', '%'.request()->input('search').'%');
                  });
            });
         })
         ->where(function (Builder $query) {
            if(0 < intval(request()->input('department_id', -1)))
               $query->where('department_id', request()->input('department_id'));
            else if(0 == intval(request()->input('department_id', -1)))
            {
               $mydeps = [];
               foreach(auth()->user()->int_departments as $dep)
                  $mydeps[] = $dep->id;
               $query->whereIn('department_id', $mydeps);
            }

            if(request()->has('showhandled') ||
               request()->has('showunhandled'))
            {
               if((1 == request()->input('showhandled', 0)) &&
                  (0 == request()->input('showunhandled', 0)))
                  $query->whereNotNull('finished_at');
               else if((0 == request()->input('showhandled', 0)) &&
                  (1 == request()->input('showunhandled', 0)))
                  $query->whereNull('finished_at');
               else if((0 == request()->input('showhandled', 0)) &&
                  (0 == request()->input('showunhandled', 0)))
                  $query->where('id',0);
            }
         })
         ->when(request()->has('context_type'), function (Builder $query) {
            $query->where('context_type','App\\Models\\'.request()->input('context_type'));

            if(request()->has('context_id'))
               $query->where('context_id',request()->input('context_id'));
         })
         ->when((0 < intval(request()->input('tag_id', 0))), function (Builder $query) {
            $query->whereHas('tags', function (Builder $query) {
               $query->where('tags.id', intval(request()->input('tag_id', 0)));
            });
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) use ($table) {
            $query->where($table.'.id', intval(request()->input('id', 0)));
         })
         ->when(0 < intval(request()->input('isnc', 0)), function (Builder $query) {
            $query->where('nonconformity', 1);
         })
         ->when(0 < count(array_filter(array_keys(request()->all()), function($var){ return (0 === strpos($var,'customproperty_')); })), function(Builder $query){ self::getIndexQuery($query); })
         ->select($table.'.*')
         ->with(['tags'])
         ->withCount(['messages'])
         ->orderBy('created_at', 'desc');

      if(request()->input('hidechecked', 0) && (new self())->status) {
         return $returnCollection->whereNull('finished_at')->get();
      }

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
         'department_id' => 'required|exists:App\Models\Department,id',
         'description' => 'required',
         'consequence' => 'nullable',
         'rootcause' => 'nullable',
         'immediateaction' => 'nullable',
         'preventativeaction' => 'nullable',
         'nonconformity' => 'nullable',
         'estimated_cost' => 'sometimes|nullable|numeric|min:0',
      ];
   }
 
   public function int_department() : BelongsTo
   {
      return $this->belongsTo(Department::class, 'department_id');
   }

   public function int_compliance_evaluation_requirement_finding() : BelongsTo
   {
      return $this->belongsTo(ComplianceEvaluationRequirementFinding::class, 'compliance_evaluation_requirement_finding_id');
  }

   /**
    * Get the control actions associated with the risk
    */
   public function int_control_actions() : BelongsToMany
   {
      return $this->belongsToMany(ControlAction::class, 'control_action_mappings', 'finding_id', 'control_action_id');
   }


   /**
    * Reopen the finding
    */
   public function reopen()
   {
      // Ensure correct authorization
      if(!request()->user()->hasPermissionTo('managementtools.edit'))
         abort(403);

      // Ensure the finding is not already open
      if(null == $this->finished_at)
         abort(400, __('The finding is not finished'));

      DB::table('findings')->where('id', $this->id)->update(['finished_at' => null]);

      ActivityLog::addMessage(__("The finding was reopened by")." ".auth()->user()->name, $this);
   }
}
