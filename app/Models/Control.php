<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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


class Control extends Model
{
   use DefersRelationAttributeSync, HasTags, HasMessages, HasNotifications, HasCustomProperties;

   public static function getPrettyName($plural = false){ if($plural) { return __("Controls"); } else { return __("Control"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      if(null != $department)
         return [];
      
      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', Control::class))
         return [];
      
      $count = Control::whereNull('not_applicable_at')->whereNull('responsible_user_id')->count();
      if(!$personalOnly && $count)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Control::getPrettyName($count > 1).' '.__("without assignment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/controls') : null];

      $count = $user ? Control::whereNull('not_applicable_at')->where('responsible_user_id', $user->id)->whereNull('statusdescription')->count() : Control::whereNull('not_applicable_at')->whereNull('statusdescription')->count();
      if($count > 0)
         $retval[] = ['level' => 'warning', 'count' => $count, 'text' => Control::getPrettyName($count > 1).' '.__("without status description"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/controls') : null];
      
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
      });
      
      static::deleting(function ($model) {
         // Delete all associated control actions (will re-open any findings or risks)
         foreach($model->int_control_actions as $obj)
            $obj->delete();
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'pending_action_count',
      'risks',
      'tags',
      'messagecount',
      'requirements',
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
      if($this->not_applicable_at)
         return ['icon' => 'visibility_off', 'level' => 'info', 'text' => __("This control is set as not applicable")];

      if(!$this->responsible_user_id)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("A responsible user has not been assigned")];
      
      if(!$this->statusdescription)
         return ['icon' => 'warning', 'level' => 'warning', 'text' => __("Status description is missing")];
      
      if($this->getPendingActionCountAttribute())
         return ['icon' => 'event_list', 'level' => 'info', 'text' => __("There are pending actions for this control")];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
      
   }
   
   public function getRequirementsAttribute()
   {
      if ($this->relationLoaded('int_requirements')) {
         return $this->getRelation('int_requirements')
            ->map(fn($obj) => ['id' => $obj->id, 'name' => $obj->name])
            ->all();
      }

      return Cache::rememberForever('Control.getRequirementsAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_requirements as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name);
         return $retval;
      });
   }
   
   public function setRequirementsAttribute($value)
   {
      $this->syncRelationAttribute('requirements', fn ($model) => $model->int_requirements()->sync($value));
   }
      
   public function getTagsAttribute()
   {
      if ($this->relationLoaded('tags')) {
         return $this->getRelation('tags');
      }

      return $this->tags()->get();
   }
   
   public function getMessagecountAttribute()
   {
      if (array_key_exists('messages_count', $this->attributes)) {
         return intval($this->attributes['messages_count']);
      }

      return $this->messages()->count();
   }
   
   public function getPendingActionCountAttribute()
   {
      if (array_key_exists('pending_action_count_db', $this->attributes)) {
         return intval($this->attributes['pending_action_count_db']);
      }

      if ($this->relationLoaded('int_control_actions')) {
         return $this->getRelation('int_control_actions')->whereNull('finished_at')->count();
      }

      return Cache::rememberForever('Control.getPendingActionCountAttribute.'.$this->id, function(){
        return $this->int_control_actions()->whereNull('finished_at')->count();
      });
   }
   
   public function getRisksAttribute()
   {
      if ($this->relationLoaded('int_risks')) {
         $retval = [];
         foreach ($this->getRelation('int_risks')->whereNull('replacedby_id') as $obj) {
            $riskOwnerName = '';
            if (null != $obj->riskowner_id) {
               $riskOwnerName = $obj->relationLoaded('int_riskowner') && $obj->int_riskowner
                  ? $obj->int_riskowner->name
                  : $obj->int_riskowner?->name;
            }

            $retval[] = [
               'id' => $obj->id,
               'name' => $obj->name_pretty,
               'riskowner' => $riskOwnerName,
               'status' => $obj->status,
            ];
         }
         return $retval;
      }

      return Cache::rememberForever('Control.getRisksAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_risks()->whereNull('replacedby_id')->distinct()->get() as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name_pretty, 'riskowner' => (null != $obj->riskowner_id) ? $obj->int_riskowner->name : '', 'status' => $obj->status);
         return $retval;
      });
   }
   


   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'created_at',
      'updated_at',
      'responsible_user_id',
      'pending_action_count',
      'risks',
      'tags',
      'messagecount',
      'requirements',
      'status',
      'statusdescription',
      'not_applicable_at',
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
      'requirements',
      'statusdescription',
   ];

   /**
    * The public actions available
    */
   public $actions = [
      'notapplicable',
      'applicable',
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
      $search = trim((string) request()->input('search', ''));
      $showMyOnly = (1 === intval(request()->input('showmyonly', 0)));
      $tagId = intval(request()->input('tag_id', 0));
      $responsibleUserId = intval(request()->input('responsible_user_id', 0));
      $showNotApplicable = (1 === intval(request()->input('shownotapplicable', 0)));
      $id = intval(request()->input('id', 0));
      $hideChecked = (bool) request()->input('hidechecked', 0);

      $query = self::query()
         ->when($search !== '', function (Builder $query) use ($search) {
            $query->where(function (Builder $q) use ($search) {
               $q->where('name', 'LIKE', '%'.$search.'%')
                  ->orWhere('description', 'LIKE', '%'.$search.'%')
                  ->orWhereHas('tags', function (Builder $tagQuery) use ($search) {
                     $tagQuery->where('name', 'LIKE', '%'.$search.'%');
                  });
            });
         })
         ->when($showMyOnly, function (Builder $query) {
            $query->where('responsible_user_id', auth()->user()->id);
         })
         ->when($tagId > 0, function (Builder $query) use ($tagId) {
            $query->whereHas('tags', function (Builder $q) use ($tagId) {
               $q->where('tags.id', $tagId);
            });
         })
         ->when($responsibleUserId > 0, function (Builder $query) use ($responsibleUserId) {
            $query->where('responsible_user_id', $responsibleUserId);
         })
         ->when(!$showNotApplicable, function (Builder $query) {
            $query->whereNull('not_applicable_at');
         })
         ->when($id > 0, function (Builder $query) use ($id) {
            $query->where((new self())->getTable().'.id', $id);
         })
         ->when(0 < count(array_filter(array_keys(request()->all()), function($var){ return (0 === strpos($var,'customproperty_')); })), function(Builder $query){ self::getIndexQuery($query); })
         ->when($hideChecked && (new self())->status, function (Builder $query) {
            $query->whereNull('not_applicable_at')
               ->where(function (Builder $q) {
                  $q->whereNull('responsible_user_id')
                     ->orWhereNull('statusdescription')
                     ->orWhere('statusdescription', '');
               });
         })
         ->with([
            'tags',
            'int_requirements:id,name',
            'int_risks.int_riskowner:id,name',
         ])
         ->withCount([
            'messages',
            'int_control_actions as pending_action_count_db' => function (Builder $q) {
               $q->whereNull('finished_at');
            },
         ])
         ->orderBy('name');

      if($hideChecked && (new self())->status) {
         return $query->get();
      }

      return $query->paginate();
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
         'description' => 'required',
         'responsible_user_id' => 'nullable|exists:App\Models\User,id',
      ];
   }
   
   public function int_responsible_user() : BelongsTo
   {
      return $this->belongsTo(User::class, 'responsible_user_id');
   }

    /**
     * Get the department associated with the control.
     */
    public function int_department() : BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }   

    /**
     * Get the risks associated with the control.
     */
    public function int_risks() : BelongsToMany
    {
        return $this->belongsToMany(Risk::class, 'control_risks', 'control_id', 'risk_id');
    }   
   
    /**
     * Get the requirements associated with the control.
     */
    public function int_requirements() : BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'control_requirements');
    }   
    
    /**
     * Get the actions associated with the control
     */
   public function int_control_actions() : HasMany
   {
      return $this->hasMany(ControlAction::class);
   }
   
   /**
    * Set this control as not applicable
    */
   public function notapplicable()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure control is not already set as not applicable
      if(null != $this->not_applicable_at)
         abort(400, __('The control is already set as not applicable'));

      // Ensure current user is responsible
      if(auth()->user()->id != $this->responsible_user_id)
         abort(400, __('Only the responsible user can set the control as not applicable'));

      DB::table('controls')->where('id', $this->id)->update(['not_applicable_at' => date("Y-m-d H:i:s")]);

      ActivityLog::addMessage(__("The control was set not applicable by")." ".auth()->user()->name, $this);
   }

   /**
    * Set this control as applicable
    */
   public function applicable()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure requirement source is not already set as applicable
      if(null == $this->not_applicable_at)
         abort(400, __('The control is already set as applicable'));

      // Ensure current user is responsible
      if(auth()->user()->id != $this->responsible_user_id)
         abort(400, __('Only the responsible user can set the control as applicable'));

      DB::table('controls')->where('id', $this->id)->update(['not_applicable_at' => null]);

      ActivityLog::addMessage(__("The control was set applicable by")." ".auth()->user()->name, $this);
   }
}
