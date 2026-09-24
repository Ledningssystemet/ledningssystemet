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
use Illuminate\Validation\Rule;
use App\Traits\HasMessages;
use App\Traits\HasNotifications;

class Activity extends Model
{
   use HasMessages, HasNotifications;
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Activities"); } else { return __("Activity"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      
      if(null != $department)
         return [];
      
      $count = Activity::whereNull('responsible_user_id')->count();
      if(!$personalOnly && (null != auth()->user()) && auth()->user()->hasAnyPermission(['managementtools.edit']) && ($count > 0))
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Activity::getPrettyName($count > 1).' '.__("without assignment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/activities') : null, 'personal' => false];
      
      
      if($user)
      {
         $count = Activity::whereNull('completed_at')->where('responsible_user_id', $user->id)->where('due', '<', date("Y-m-d"))->count();
         if(0 < $count)
            $retval[] = ['level' => 'danger', 'count' => $count, 'text' => __("Overdue").' '.strtolower(Activity::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/activities') : null, 'personal' => true];

         $count = Activity::whereNull('completed_at')->where('responsible_user_id', $user->id)->where('due', '>=', date("Y-m-d"))->count();
         if(0 < $count)
            $retval[] = ['level' => 'info', 'count' => $count, 'text' => __("Pending").' '.strtolower(Activity::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/activities') : null, 'personal' => true];
      }
      else
      {
         $count = Activity::whereNull('completed_at')->where('due', '<', date("Y-m-d"))->count();
         if(0 < $count)
            $retval[] = ['level' => 'warning', 'count' => $count, 'text' => __("Overdue").' '.strtolower(Activity::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/activities') : null, 'personal' => false];

         $count = Activity::whereNull('completed_at')->where('due', '>=', date("Y-m-d"))->count();
         if(0 < $count)
            $retval[] = ['level' => 'info', 'count' => $count, 'text' => __("Pending").' '.strtolower(Activity::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/activities') : null, 'personal' => false];
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
      
      static::saved(function ($model) {
         // Check if pending activities need to start when this activity is finished
         if($model->wasChanged('completed_at') && $model->completed_at && (null != $model->activity_flow_id))
         {
            foreach(PendingActivity::where('dependant_activity_id', $model->id)->get() as $obj)
            {
               // Create new activity from pending activity
               $templateitem = $obj->int_activity_flow_template_item;
               $act = new Activity();
               $act->name = $templateitem->name;
               $act->description = $obj->description;
               $act->responsible_user_id = $obj->responsible_user_id;
               $act->activity_flow_id = $obj->activity_flow_id;
               $act->activity_flow_template_item_id = $obj->activity_flow_template_item_id;
               $act->due = date("Y-m-d", strtotime("+".$templateitem->dueoffsetdays." DAYS"));
               $act->save();
               
               // Update all pending activities depending on this pending activity
               PendingActivity::where('dependant_pending_activity_id', $obj->id)->update(['dependant_pending_activity_id' => null, 'dependant_activity_id' => $act->id]);
               
               // Delete the pending activity
               $obj->delete();
            }
         }
      });
      
      // Prevent deletion of activities being part of a flow
      static::deleting(function ($model) {
         if($model->activity_flow_id)
            abort(400, __("You may not delete an activity that is part of an activity flow"));
      });
   }
   

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'messagecount',
      'intervaltypetext',
      'status',
      'activity_flow_name',
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
      if($this->completed_at)
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
         
      if(!$this->responsible_user_id)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("A responsible user has not been assigned")];
      
      if((strtotime($this->due) < time()) && (null != request()->user()) && ($this->responsible_user_id == request()->user()->id))
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("Activitiy is overdue")];
         
      if(strtotime($this->due) < time())
         return ['icon' => 'warning', 'level' => 'warning', 'text' => __("Activitiy is overdue")];
      
      return ['icon' => 'pending_actions', 'level' => 'info', 'text' => ''];
      
   }
   
   public function getMessagecountAttribute()
   {
      return $this->messages()->count();
   }
      
   public function getIntervaltypetextAttribute()
   {
      return __(strtolower($this->intervaltype));
   }
   
   public function getActivityFlowNameAttribute()
   {
      return (null == $this->activity_flow_id) ? "" : $this->int_activity_flow->name;
   }


   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'due',
      'intervalnum',
      'intervaltype',
      'completed_at',
      'created_at',
      'updated_at',
      'responsible_user_id',
      'messagecount',
      'intervaltypetext',
      'activity_flow_id',
      'activity_flow_name',
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
      'responsible_user_id',
      'due',
      'finished_at',
      'intervalnum',
      'intervaltype'
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
      'completed',
   ];  
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(!$user->hasAnyPermission(['managementtools.edit']) || (1 == intval(request()->input('showmyonly', 0))), function (Builder $query) use ($user) {
            $query->where('responsible_user_id', $user->id);
         })
         ->when((0 == request()->input('showcompleted', 0)), function (Builder $query) {
            $query->whereNull('completed_at');
         })
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('due');
         
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
         'name' => ['required'],
         'responsible_user_id' => 'nullable|exists:users,id',
         'due' => 'required',
         'intervalnum' => 'sometimes|numeric|min:0|max:999',
      ];
   }
   
   public function int_responsible_user() : BelongsTo
   {
      return $this->belongsTo(User::class, 'responsible_user_id');
   }   
    
   public function int_activity_flow() : BelongsTo
   {
      return $this->belongsTo(ActivityFlow::class, 'activity_flow_id');
   }   
   
   /**
    * Completed
    */
   public function completed()
   {
      if(request()->user()->cannot('update', $this))
         abort(403);
      
      // Ensure correct responsible
      if((null == $this->responsible_user_id) ||
         ($this->responsible_user_id != auth()->user()->id))
      {
         abort(401, __("You are not the person responsible for this activity and can therefore not mark it as complete"));
      }
      
      // Ensure not already finished
      if(null != $this->completed_at)
         abort(400, 'The activity has already been completed');
     
      $this->completed_at = date("Y-m-d H:i:s");
      $this->update();
      
      // Create new instance if this is a recurring item
      if(null != $this->intervaltype)
      {
         $newactivity = $this->replicate(['completed_at']);
         $newactivity->due = date("Y-m-d", strtotime("+".$newactivity->intervalnum.' '.$newactivity->intervaltype));
         $newactivity->save();
      }
   }

}
