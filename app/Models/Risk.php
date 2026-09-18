<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
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
use App\Http\Controllers\UserNotificationController;
use App\Models\Concerns\DefersRelationAttributeSync;

class Risk extends Model
{
   use DefersRelationAttributeSync, HasTags, HasMessages, HasNotifications, HasCustomProperties;

   public static function getPrettyName($plural = false){ if($plural) { return __("Risks"); } else { return __("Risk"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      
      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', Risk::class))
         return [];
      
      $count = ((null == $department) ? 
            Risk::whereNull('riskowner_id')->whereNull('assessed_at')->whereNull('replacedby_id')->whereNull('risk_project_id')->count() : 
            Risk::whereNull('riskowner_id')->whereNull('assessed_at')->whereNull('replacedby_id')->where('department_id', $department->id)->whereNull('risk_project_id')->count()
         );
      if(!$personalOnly && $count)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Risk::getPrettyName($count > 1).' '.__("without an assigned risk owner"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/riskregister') : null];
      
      $count = (null == $user) ?
         (
            (null == $department) ?
               Risk::whereNull('assessed_at')->whereNull('replacedby_id')->whereNull('risk_project_id')->count() :
               Risk::where('department_id', $department->id)->whereNull('assessed_at')->whereNull('replacedby_id')->whereNull('risk_project_id')->count()
         ) :
         (
            (null == $department) ?
               Risk::whereNull('assessed_at')->where('riskowner_id', $user->id)->whereNull('replacedby_id')->whereNull('risk_project_id')->count() :
               Risk::whereNull('assessed_at')->whereNull('replacedby_id')->where('department_id', $department->id)->where('riskowner_id', $user->id)->whereNull('risk_project_id')->count());

      if($count)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $count, 'text' => Risk::getPrettyName($count > 1).' '.__("pending assessment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/riskregister') : null];
      
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
         
         // If part of a project, make sure project is not archived
         if(null != $model->risk_project_id)
         {
            if(null != $model->int_risk_project->archived_at)
               abort(400, __("You cannot modify any risks for an archived risk project"));
         }
      });
static::creating(function ($model) {
                  $model->created_by = (null != auth()->user()) ? auth()->user()->id : null;
      });
      
      static::deleting(function ($model) {
         
         if((null != auth()->user()) && !auth()->user()->hasAnyPermission(['riskadministrator.edit', 'superadmin.edit']))
         {
            // Prevent deletion if risk is already assessed
            if(null != $model->assessed_at)
               abort(400, __("You cannot delete an assessed risk"));
            
            // If part of a project, make sure project is not archived
            if(null != $model->risk_project_id)
            {
               if(null != $model->int_risk_project->archived_at)
                  abort(400, __("You cannot delete a risk for an archived risk project"));
            }
         }
      });
      
      // Log delete
      static::deleted(function ($model) {
         if(null != $model->replacing_id)
         {
            $riskobj = Risk::find($model->replacing_id);
            if(null != $riskobj)
            {
               // Create history message
               ActivityLog::addMessage(__("The risk RISK-".$model->id." was deleted, thus re-activating this risk assessment"), $riskobj);
            }
         }

         if($model->risk_project_id)
            ActivityLog::addMessage(__("The risk RISK-") . $model->id . " " . __("was deleted by") . " " . ((null == auth()->user()) ? "SYSTEM" : auth()->user()->name), $model->int_risk_project);
      });

      static::created(function ($model) {
         if($model->risk_project_id)
            ActivityLog::addMessage(__("The risk RISK-") . $model->id . " " . __("was added by") . " " . ((null == auth()->user()) ? "SYSTEM" : auth()->user()->name), $model->int_risk_project);
      });

      static::updated(function ($model) {
         if($model->risk_project_id)
            ActivityLog::addMessage(__("The risk RISK-") . $model->id . " " . __("was updated by") . " " . ((null == auth()->user()) ? "SYSTEM" : auth()->user()->name), $model->int_risk_project);

      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'updatedts',
      'context',
      'risk_controls',
      'created_at_pretty',
      'status',
      'name_pretty',
      'scenariodescription_pretty',
      'consequencedescription_pretty',
      'tags',
      'messagecount',
      'risk_level',
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
      if(null === $this->riskowner_id)
         return ['icon' => 'report', 'level' => 'danger', 'text' => __("This risk has not been assigned to a riskowner")];

      // If part of a risk project, ensure that the assigned risk owner is part of the project
      if(null != $this->risk_project_id)
      {
         $proj = $this->int_risk_project;
         if(null != $proj)
         {
            $isProjectMember = ($this->riskowner_id == $proj->responsible_user_id);
            if(!$isProjectMember)
            {
               $isProjectMember = $proj->relationLoaded('int_users')
                  ? $proj->int_users->contains('id', $this->riskowner_id)
                  : $proj->int_users()->where('users.id', $this->riskowner_id)->exists();
            }

            if(!$isProjectMember)
               return ['icon' => 'report', 'level' => 'danger', 'text' => __("The assigned risk owner is not part of the risk project and will not be able to see or assess this risk")];
         }
      }
      
      if(!$this->assessed_at && (null != request()->user()) && ($this->riskowner_id == request()->user()->id))
         return ['icon' => 'report', 'level' => 'danger', 'text' => __("This risk is pending assessment")];
         
      if(!$this->assessed_at)
         return ['icon' => 'report', 'level' => 'warning', 'text' => __("This risk is pending assessment")];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }

   
   public function getCreatedAtPrettyAttribute()
   {
      return date("Y-m-d H:i", strtotime($this->created_at));
   }
   
   public function getUpdatedtsAttribute()
   {
      return date("Y-m-d H:i", strtotime($this->updated_at));
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
            case 'App\\Models\\Department':
            case 'App\\Models\\Process':
            case 'App\\Models\\Asset':
            case 'App\\Models\\InformationType':
            case 'App\\Models\\Supplier':
            case 'App\\Models\\ProcessActivity':
            case 'App\\Models\\Site':
            case 'App\\Models\\Customer':
               break;
            default:
               abort(400, __("Invalid context type"));
         }
         
         $this->context_type = $classname;
         
         $classInstance = $this->context_type::findOrFail($contextParts[1]);
         $this->context_id = $classInstance->id;
      }
   }
   
   public function getRiskControlsAttribute()
   {
      if ($this->relationLoaded('int_controls')) {
         return $this->int_controls
            ->map(fn($obj) => ['id' => $obj->id, 'name' => $obj->name])
            ->all();
      }

      return Cache::rememberForever('Risk.getRiskControlsAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_controls as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name);
         return $retval;
      });
   }
   
   public function setRiskControlsAttribute($value)
   {
      $this->syncRelationAttribute('riskcontrols', fn ($model) => $model->int_controls()->sync($value));
   }

   public function getContextObjectName()
   {
      return Cache::rememberForever('Risk.getContextObjectName.'.$this->context_type.'.'.$this->context_id, function(){
         if((null == $this->context_type) || (null == $this->context_id) || ('App\\Models\\Company' == $this->context_type))
            return Company::getName();

         if ($this->relationLoaded('contextObject')) {
            $contextObject = $this->getRelation('contextObject');
            return $contextObject ? $contextObject->name : null;
         }

         static $contextNameCache = [];
         $cacheKey = $this->context_type.':'.$this->context_id;
         if (array_key_exists($cacheKey, $contextNameCache)) {
            return $contextNameCache[$cacheKey];
         }

         if (!class_exists($this->context_type)) {
            $contextNameCache[$cacheKey] = null;
            return null;
         }
         
         $contextNameCache[$cacheKey] = $this->context_type::query()
            ->whereKey($this->context_id)
            ->value('name');
         
         return $contextNameCache[$cacheKey];
      });      
   }

    /**
      Get name
     */
   public function getNamePrettyAttribute()
   {
      $retval = $this->name;
      
      // Check if any replacements are to be done
      if(false === stripos($retval, '{name}'))
         return $retval;
      
      $coname = $this->getContextObjectName();
      if(null == $coname)
         return $retval;
      
      $retval = str_replace('{name}', $coname, $retval);
      $retval = str_replace('{Name}', ucfirst($coname), $retval);
      $retval = str_replace('{NAME}', strtoupper($coname), $retval);
      return $retval;
   }

    /**
      Get scenariodescription
     */
   public function getScenariodescriptionPrettyAttribute()
   {
     $retval = $this->scenariodescription;
      
      // Check if any replacements are to be done
      if(false === stripos($retval, '{name}'))
         return $retval;
      
      $coname = $this->getContextObjectName();
      if(null == $coname)
         return $retval;
      
      $retval = str_replace('{name}', $coname, $retval);
      $retval = str_replace('{Name}', ucfirst($coname), $retval);
      $retval = str_replace('{NAME}', strtoupper($coname), $retval);
      return $retval;
   }

    /**
      Get consequencedescription
     */
   public function getConsequencedescriptionPrettyAttribute()
   {
     $retval = $this->consequencedescription;
     
      // Check if any replacements are to be done
      if(false === stripos($retval, '{name}'))
         return $retval;
     
      $coname = $this->getContextObjectName();
      if(null == $coname)
         return $retval;
      
      $retval = str_replace('{name}', $coname, $retval);
      $retval = str_replace('{Name}', ucfirst($coname), $retval);
      $retval = str_replace('{NAME}', strtoupper($coname), $retval);
      return $retval;
   }
   
   public function getTagsAttribute()
   {
      if ($this->relationLoaded('tags')) {
         return $this->getRelation('tags');
      }

      return Cache::rememberForever('Risk.getTagsAttribute.'.$this->id, function(){
         return $this->tags()->get();
      });
   }
   
   public function getMessagecountAttribute()
   {
      if (array_key_exists('messages_count', $this->attributes)) {
         return intval($this->attributes['messages_count']);
      }

      if ($this->relationLoaded('messages')) {
         return $this->messages->count();
      }

      return $this->messages()->count();
   }

   public function getRisklevelAttribute()
   {
      if ($this->relationLoaded('int_probability') && $this->relationLoaded('int_consequence')) {
         if (null == $this->assessed_at)
            return null;

         $risklevel = RiskLevel::getRisklevel($this->int_probability, $this->int_consequence);
         if (null == $risklevel)
            return null;

         return ['id' => $risklevel->id, 'name' => $risklevel->name, 'color' => $risklevel->color, 'ordinal' => $risklevel->ordinal];
      }

      return Cache::rememberForever('Risk.getRisklevelAttribute.'.$this->id, function() {
         if (null == $this->assessed_at)
            return null;

         $risklevel = $this->int_risklevel();
         if (null == $risklevel)
            return null;
         return ['id' => $risklevel->id, 'name' => $risklevel->name, 'color' => $risklevel->color, 'ordinal' => $risklevel->ordinal];
      });
   }
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'context_type',
      'department_id',
      'context_id',
      'scenariodescription',
      'consequencedescription',
      'riskowner_id',
      'replacing_id',
      'replacedby_id',
      'assessed_at',
      'replaced_at',
      'created_at',
      'updated_at',
      'created_by',
      'probability_id',
      'consequence_id',
      'status',
      'updatedts',
      'context',
      'risk_controls',
      'created_at_pretty',
      'name_pretty',
      'scenariodescription_pretty',
      'consequencedescription_pretty',
      'assessmentcomment',
      'risk_project_id',
      'tags',
      'messagecount',
      'risk_level',
   ];

   
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'department_id',
      'scenariodescription',
      'consequencedescription',
      'riskowner_id',
      'probability_id',
      'consequence_id',
      'context',
      'risk_controls',
      'assessmentcomment',
      'risk_project_id',
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
      'ignore',
      'approve',
      'replace',
      'detach',
   ];
   
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      // Check if user is allowed to access risk project before listing any risks
      if((null != $user) &&
         (!$user->hasAnyPermission(['riskadministrator.edit', ''])) &&
         (0 < intval(request()->input('risk_project_id', '0'))))
      {
         $proj = RiskProject::findOrFail(intval(request()->input('risk_project_id')));
         if(($proj->responsible_user_id != $user->id) &&
            (false === array_search($user->id, $proj->int_users()->pluck('users.id')->toArray())))
            abort(403);
      }
      
      $contexttype = request()->input('context_type');
      if(0 == $contexttype)
         $contexttype = null;
      
      if($contexttype && (false === strpos($contexttype, 'App\\Models\\')))
         $contexttype = 'App\\Models\\'.$contexttype;
      
      $returnCollection = (__CLASS__)::whereNull('replacedby_id')
         ->where(function (Builder $query) {
            $query->when(!request()->has('context_id') && (request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               
               // Find objects
               $objs = [];
               foreach(Process::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('processes.id') as $objid) $objs[] = 'App\\Models\\Process:'.$objid;
               foreach(Department::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('departments.id') as $objid) $objs[] = 'App\\Models\\Department:'.$objid;
               foreach(ProcessActivity::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('process_activities.id') as $objid) $objs[] = 'App\\Models\\ProcessActivity:'.$objid;
               foreach(Supplier::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('suppliers.id') as $objid) $objs[] = 'App\\Models\\Supplier:'.$objid;
               foreach(Customer::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('customers.id') as $objid) $objs[] = 'App\\Models\\Customer:'.$objid;
               foreach(Asset::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('assets.id') as $objid) $objs[] = 'App\\Models\\Asset:'.$objid;
               foreach(InformationType::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('information_types.id') as $objid) $objs[] = 'App\\Models\\InformationType:'.$objid;
               foreach(Site::where('name', 'LIKE', '%'.request()->input('search').'%')->pluck('sites.id') as $objid) $objs[] = 'App\\Models\\Site:'.$objid;
               
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                     ->orWhere('risks.id', str_ireplace('RISK-', '', request()->input('search')))
                     ->orWhere('scenariodescription', 'LIKE', '%'.request()->input('search').'%')
                     ->orWhere('consequencedescription', 'LIKE', '%'.request()->input('search').'%')
                     ->orWhereHas('tags', function (Builder $query) {
                        $query->where('name', 'LIKE', '%'.request()->input('search').'%');
                     })
                     ->orWhereIn(DB::raw('CONCAT(context_type,\':\',context_id)'), $objs);
            });
         })
         ->when(0 < request()->input('probability_id', 0), function (Builder $query) {
            $query->where('probability_id', request()->input('probability_id', 0));
         })
         ->when(0 < request()->input('consequence_id', 0), function (Builder $query) {
            $query->where('consequence_id', request()->input('consequence_id', 0));
         })
         ->when(0 < request()->input('risk_level_id', 0), function (Builder $query) {
            $query->leftJoin('risk_level_mappings', function (JoinClause $join){
                  $join->on('risk_level_mappings.probability_level_id', '=', 'risks.probability_id')
                       ->on('risk_level_mappings.consequence_level_id', '=', 'risks.consequence_id');
               })
               ->where('risk_level_mappings.risk_level_id', request()->input('risk_level_id', 0));
         })
         ->where(function (Builder $query) use($user) {
            if(0 < intval(request()->input('department_id', -1)))
               $query->where('department_id', request()->input('department_id'));
            else if(0 == intval(request()->input('department_id', -1)))
            {
               $mydeps = [];
               foreach(auth()->user()->int_departments as $dep)
                  $mydeps[] = $dep->id;
               $query->whereIn('department_id', $mydeps);
            }

            if(request()->has('showapproved') ||
               request()->has('showdraft'))
            {
               if((1 == request()->input('showapproved', 0)) &&
                  (0 == request()->input('showdraft', 0)))
                  $query->whereNotNull('assessed_at');
               else if((0 == request()->input('showapproved', 0)) &&
                  (1 == request()->input('showdraft', 0)))
                  $query->whereNull('assessed_at');
               else if((0 == request()->input('showapproved', 0)) &&
                  (0 == request()->input('showdraft', 0)))
                  $query->where('risks.id',0);
            }
            
            
            
            // Authorization
            if((0 >= intval(request()->input('risk_project_id', '0'))) && !$user->hasAnyPermission(['riskadministrator.edit', 'riskall.edit']))
            {
               $query->where(function (Builder $subquery) use($user) {
                  $subquery->where('riskowner_id', $user->id);
                  
                  if($user->hasAnyPermission(['riskdepartment.edit']))
                     $subquery->orWhereIn('department_id', $user->int_departments()->pluck('departments.id'));
               });                  
            }
         })
         ->when((1 == request()->input('showmyonly', 0)), function (Builder $query) use($user) { 
            $query->where('riskowner_id', $user->id);
         })
         ->when(0 < intval(request()->input('riskowner_id', 0)), function (Builder $query) {
            $query->where('riskowner_id', intval(request()->input('riskowner_id', 0)));
         })
         ->when($contexttype, function (Builder $query) use ($contexttype) {
            $query->where('context_type',$contexttype);
            
            if(request()->has('context_id'))
               $query->where('context_id',request()->input('context_id'));
         
         })
         ->where(function (Builder $query){
            if(0 < intval(request()->input('risk_project_id', '0')))
               $query->where('risk_project_id',request()->input('risk_project_id'));
            else
               $query->whereNull('risk_project_id');

         })
         ->when((0 < intval(request()->input('tag_id', 0))), function (Builder $query) {
            $query->whereHas('tags', function (Builder $query) {
                  $query->where('tags.id', intval(request()->input('tag_id', 0)));
            });
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(0 < count(array_filter(array_keys(request()->all()), function($var){ return (0 === strpos($var,'customproperty_')); })), function(Builder $query){ (__CLASS__)::getIndexQuery($query); })
         ->select('risks.*')
         ->with([
            // contextObject laddas manuellt nedan för att undvika exception mot Company (ingen tabell)
            'int_controls:id,name',
            'tags',
            'int_probability:id,name,ordinal',
            'int_consequence:id,name,ordinal',
            'int_risk_project:id,responsible_user_id',
            'int_risk_project.int_users:id',
         ])
         ->withCount('messages')
         ->orderBy('name');

      if(request()->input('hidechecked', 0) && (new (__CLASS__))->status) {
         $results = $returnCollection->get()->filter(fn($item) => $item->status['level'] !== 'info');
         self::eagerLoadContextObjects($results);
         return $results;
      }

      $paginator = $returnCollection->paginate();
      self::eagerLoadContextObjects($paginator->getCollection());
      return $paginator;
   }
   
    
   /**
    * Batch-loads the contextObject relation, skipping Company (which has no table).
    * Risks with context_type = Company (or null) get the relation set to null directly
    * so that getContextObjectName() can return Company::getName() without a DB query.
    */
   private static function eagerLoadContextObjects(\Illuminate\Database\Eloquent\Collection $collection): void
   {
      // Set contextObject = null for Company/null types so accessors short-circuit cleanly
      $collection->filter(fn($r) => empty($r->context_type) || $r->context_type === Company::class)
         ->each(fn($r) => $r->setRelation('contextObject', null));

      // Eager-load for all other types in one batch per type (same as Eloquent's MorphTo, but safe)
      $collection->filter(fn($r) => !empty($r->context_type) && $r->context_type !== Company::class)
         ->load('contextObject');
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
         'department_id' => 'nullable|exists:App\Models\Department,id',
         'riskowner_id' => 'nullable|exists:App\Models\User,id',
         'probability_id' => 'nullable|exists:App\Models\ProbabilityLevel,id',
         'consequence_id' => 'nullable|exists:App\Models\ConsequenceLevel,id',
         'risk_project_id' => 'nullable|exists:App\Models\RiskProject,id',
      ];
   }

   public function int_department() : BelongsTo
   {
      return $this->belongsTo(Department::class, 'department_id');
   }


   public function int_replacing() : BelongsTo
   {
      return $this->belongsTo(Risk::class, 'replacing_id');
   }

   public function int_replacedby() : BelongsTo
   {
      return $this->belongsTo(Risk::class, 'replacedby_id');
   }


    /**
     * Get the controls associated with the risk
     */
    public function int_controls() : BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_risks', 'risk_id', 'control_id')->distinct();
    }   
    
    /**
     * Get the control actions associated with the risk
     */
    public function int_control_actions() : BelongsToMany
    {
        return $this->belongsToMany(ControlAction::class, 'control_action_mappings', 'risk_id', 'control_action_id');
    }   
    
    /**
     * Get the probability object
     */
    public function int_probability() : BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'probability_id');
    }   
    
    /**
     * Get the consequence object
     */
    public function int_consequence() : BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'consequence_id');
    }   
    
    /**
     * Get the risk owner
     */
    public function int_riskowner() : BelongsTo
    {
        return $this->belongsTo(User::class, 'riskowner_id');
    }
    
    /**
     * Get the risk project
     */
    public function int_risk_project() : BelongsTo
    {
       return $this->belongsTo(RiskProject::class, 'risk_project_id');
    }
   
   /**
     * Get the risk level
     */
    public function int_risklevel()
    {
       return RiskLevel::getRisklevel($this->int_probability, $this->int_consequence);
    }

   /**
    * Get the associated object, if it exists
    */
   public function contextObject(): MorphTo
   {
      return $this->morphTo('contextObject', 'context_type', 'context_id');
   }

   public function int_context_object()
   {
      if(null == $this->context_type)
         return null;

      if(!class_exists($this->context_type))
         return null;

      if('App\\Models\\Company' == $this->context_type)
         return null;

      if ($this->relationLoaded('contextObject')) {
         return $this->getRelation('contextObject');
      }

      return $this->contextObject()->first();
   }

   /**
    Get status text
    */
   public function getStatusText()
   {
      return Cache::rememberForever('Risk.getStatusText.'.$this->id, function(){
         if(null == $this->assessed_at)
            return __("pending assessment");
         else if(null == $this->replacedby_id)
            return __("replaced");
         else
         {
            foreach($this->int_control_actions as $action)
            {
               if(null == $action->finished_at)
                  return __("pending mitigation");
            }
            
            return __("accepted");
         }
      });
   }
   
   /**
    * Approve this risk
    */
   public function approve()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);
      
      // Ensure current user is the risk owner
      if(auth()->user()->id != $this->riskowner_id)
         abort(400, __('Only the risk owner can approve a risk'));
      
      // Ensure a risk has not been approved already
      if(null != $this->assessed_at)
         abort(400, __('The risk has already been approved'));
     
      // Ensure risk is assessed
      if((null == $this->probability_id) ||
         (null == $this->consequence_id))
         abort(400, __("A risk may not be approved without being assessed"));
      
      // Ensure risk owner is authorized
      $risklevel = RiskLevel::getRisklevel(ProbabilityLevel::findOrFail($this->probability_id), ConsequenceLevel::findOrFail($this->consequence_id));
      if(null == $risklevel)
         abort(400, __('Could not derive risk level based on the current assessment'));
      
      // Get user authorization
      $useracceptancelevel = auth()->user()->risklevel();
      if((null == $useracceptancelevel) ||
         ($useracceptancelevel->ordinal < $risklevel->ordinal))
         abort(400, __('You are not authorized to approve risks on this level, but need to escalate the risk to higher management'));
      
      
      $this->assessed_at = date("Y-m-d H:i:s");
      $this->save();
   }
   

   /**
    * Replace this risk
    */
   public function replace($reason = null)
   {
      // Ensure correct authorization
      if((null != request()->user()) && !request()->user()->can('update', $this))
         abort(403);
      
      // Ensure that the risk is assessed
      if(null == $this->assessed_at)
         abort(400, __('The risk is not assessed and can therefore not be replaced'));
     
      // Ensure that the risk is not already replaced
      if(null != $this->replacedby_id)
         abort(400, __('The risk is already replaced with another risk assessement'));
    
      // Create risk clone
      $newrisk = $this->replicate(['assessed_at', 'replacing', 'replacedby_id','replaced_at']);
      $newrisk->replacing_id = $this->id;
      $newrisk->save();
      
      // Set pointers
      $this->replacedby_id = $newrisk->id;
      $this->replaced_at = date("Y-m-d H:i:s");
      $this->save();
      
      // Sync controls and control actions
      $controls = $this->int_controls()->pluck('controls.id')->toArray();
      $newrisk->int_controls()->sync($controls);
      $controlactions = $this->int_control_actions()->pluck('control_actions.id')->toArray();
      $newrisk->int_control_actions()->sync($controlactions);

      // Create history message
      if(null == $reason)
      {
         ActivityLog::addMessage(__("The risk RISK-").$newrisk->id." ".__("was created as a reassessment of this risk"), $this);
         ActivityLog::addMessage(__("This risk is a reassessment of RISK-").$this->id, $newrisk);
      }
      else
      {
         ActivityLog::addMessage(__("The risk RISK-").$newrisk->id." ".__("was created as a reassessment of this risk due to")." ".$reason, $this);
         ActivityLog::addMessage(__("This risk is a reassessment of RISK-").$this->id." ".__("that was created due to")." ".$reason, $newrisk);
      }
   }   
   
   /**
    * Detach this risk from project
    */
   public function detach()
   {
      // Ensure that the risk is not already detached
      if(null == $this->risk_project_id)
         abort(400, __('The risk is not part of a risk project'));
      
      // Ensure correct authorization
      if(!request()->user()->hasAnyPermission(['riskadministrator.edit']) &&
         !request()->user()->can('update', $this->int_risk_project))
         abort(403);
      
      // Add note
      ActivityLog::addMessage(__("This risk was created in risk project")." ".$this->int_risk_project->name." ".__("but was detached from the project"), $this);
      $this->risk_project_id = null;
      $this->save();
   }   

   /**
    * Ignore this risk. This is only possible for partner-provided risks.
    */
   public function ignore()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);
      
      // Ensure this is a partner-provided risk
      if(null == $this->partner_object_uid)
         abort(400, __('Only partner-provided risks may be ignored'));
      
      // Ensure a risk has not been approved already
      if(null != $this->assessed_at)
         abort(400, __('Ignoring a risk is not possible after it has been assessed'));
      
      // Create new ingored-risks entry
      \Illuminate\Support\Facades\DB::table('ignored_risks')->insert([
         'risk_id' => $this->id,
         'name' => $this->name_pretty,
         'scenariodescription' => $this->scenariodescription_pretty,
         'partner_object_uid' => $this->partner_object_uid,
         'context_type' => $this->context_type,
         'context_id' => $this->context_id,
         'created_by' => (null != auth()->user()) ? auth()->user()->name : null,
         'created_at' => date("Y-m-d H:i:s"),
         'updated_at' => date("Y-m-d H:i:s"),
      ]);
      
      // Delete this risk
      $this->delete();
   }

   /**
    * Housekeeping. Delete risks where objects no longer exist
    */
   public static function deleteObsoleteRisks()
   {
      do {
         $deletecount = 0;
         foreach(Risk::whereNull('replacedby_id')->whereNotNull('context_type')->whereNotNull('context_id')->whereNotLike('context_type', '%\\Company')->get() as $risk)
         {
            // Check if object exists
               if (null == $risk->context_type::find($risk->context_id)) {
                  $objtypename = $risk->context_type;
                  if (class_exists($risk->context_type) && method_exists($risk->context_type, 'getPrettyName'))
                     $objtypename = $risk->context_type::getPrettyName();

                  ActivityLog::addMessage(__("The risk RISK-") . $risk->id . " " . __("was deleted because assessment object") . " " . $objtypename . " " . __("with ID") . " " . $risk->context_id . " " . __("has been deleted"), $risk);
                  DB::table('risks')->where('id', $risk->id)->delete();
                  $deletecount++;
               }
         }
      } while (0 < $deletecount);

      return;
   }

   /**
    * Automatically assign risk owners for untouched risks that are missing one
    */
   public static function autoAssignRiskOwners()
   {
      foreach(Risk::whereNull('riskowner_id')->whereNull('replacedby_id')->whereNull('risk_project_id')->whereRaw('created_at=updated_at')->get() as $risk)
      {
         // Fetch related context object
         $contextobj = null;
         $riskowner = null;
         if((null != $risk->context_type) && (null != $risk->context_id)) {
            $contextobj = $risk->context_type::where('id', $risk->context_id)->first();

            if(null == $contextobj)
               continue;

            // Check if the context object has a responsible user
            if(null != $contextobj->responsible_user_id)
            {
               $responsiblerisklevel = User::where('id', $contextobj->responsible_user_id)->first()->risklevel();
               if(null != $responsiblerisklevel)
                  $riskowner = User::where('id', $contextobj->responsible_user_id)->first();
            }
            else if(null != $contextobj->responsible_id)
            {
               $responsiblerisklevel = User::where('id', $contextobj->responsible_id)->first()->risklevel();
               if(null != $responsiblerisklevel)
                  $riskowner = User::where('id', $contextobj->responsible_id)->first();
            }

            // If riskowner is set, ensure that it is a member of the department set for the risk
            if(null != $riskowner) {
               $isMember = false;
               foreach ($riskowner->int_departments as $dep) {
                  if ($dep->id == $risk->department_id) {
                     $isMember = true;
                     break;
                  }
               }

               if ($isMember) {
                  DB::table('risks')->where('id', $risk->id)->update(['riskowner_id' => $riskowner->id], ['timestamps' => false]);

                  ActivityLog::addMessage(__("The risk RISK-") . $risk->id . " " . __("was automatically assigned to user") . " " . $riskowner->name . " " . __("as risk owner"), $risk);
               }
               else {
                  $riskowner = null;
               }
            }
         }

         // If no riskowner was found through the context object, try to assign a risk owner based on department and assign the department user with highest risk approval mandate
         if(null == $riskowner) {
            $highestRiskLevel = null;
            foreach ($risk->int_department->int_users as $depuser) {
               $userRiskLevel = $depuser->risklevel();
               if (null != $userRiskLevel) {
                  if ((null == $highestRiskLevel) || ($userRiskLevel->ordinal > $highestRiskLevel->ordinal)) {
                     $highestRiskLevel = $userRiskLevel;
                     $riskowner = $depuser;
                  }
               }
            }

            if (null != $riskowner) {
               DB::table('risks')->where('id', $risk->id)->update(['riskowner_id' => $riskowner->id], ['timestamps' => false]);

               ActivityLog::addMessage(__("The risk RISK-") . $risk->id . " " . __("was automatically assigned to user") . " " . $riskowner->name . " " . __("as risk owner"), $risk);
            }
         }
      }
   }
}
