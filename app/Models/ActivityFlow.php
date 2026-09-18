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
use Illuminate\Support\Facades\DB;


class ActivityFlow extends Model
{
      
   public static function getPrettyName($plural = false){ if($plural) { return __("Activity flows"); } else { return __("Activity flow"); }}
   
   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

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
      'activities',
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
      if($this->int_activities()->whereNull('completed_at')->exists())
         return ['icon' => 'pending_actions', 'level' => 'warning', 'text' => __("There are activities pending in this flow")];

      return ['icon' => 'check', 'level' => 'info', 'text' => __("All activities are finished")];
   }
   
   public function getActivitiesAttribute()
   {
      $activities = [];
      $ongoing = $this->int_activities;
      $pending = $this->int_pending_activities;
      
      foreach($this->int_activity_flow_template_items()->where('type', 'item')->orderBy('ordinal')->get() as $item)
      {
         $act = [
            'name' => $item->name,
         ];
         
         $found = false;
         foreach($ongoing as $obj)
         {
            if($item->id == $obj->activity_flow_template_item_id)
            {
               $act['responsible'] = User::find($obj->responsible_user_id);
               if(null != $act['responsible'])
                  $act['responsible'] = $act['responsible']->name;
               $act['status'] = 'ongoing';
             
               $act['due'] = $obj->due;
               $act['completed_at'] = $obj->completed_at;

               $found = true;
               break;
            }
         }
         
         if(!$found)
         {
            foreach($pending as $obj)
            {
               if($item->id == $obj->activity_flow_template_item_id)
               {
                  $act['responsible'] = User::find($obj->responsible_user_id);
                  if(null != $act['responsible'])
                     $act['responsible'] = $act['responsible']->name;
                  
                  $act['status'] = 'pending';
                  $act['due'] = $obj->int_activity_flow_template_item->dueoffsetdays.' '.__("days after completion of activity").' '.$obj->int_dependant_activity->name;

                  $found = true;
                  break;
               }
            }
         }
         
         $activities[] = $act;
      }
      
      return $activities;
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
      'activity_flow_template_id',
      'started_at',
      'status',
      'activities',
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
      'activity_flow_template_id',
      'started_at',
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
      $returnCollection = (__CLASS__)::
         where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('activity_flows.name', 'LIKE', '%'.request()->input('search').'%')
                     ->orWhere('activity_flows.description', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when(!auth()->user()->hasAnyPermission(['managementtools.edit']) || (1 == intval(request()->input('showmyonly', 0))), function (Builder $query) {
            $query->where('activity_flows.responsible_user_id',auth()->user()->id);
         })
         ->when((1 == request()->input('hidecompleted', 0)), function (Builder $query) {
            $query
               ->leftJoin('activities', 'activities.activity_flow_id', '=', 'activity_flows.id')
               ->whereNull('activities.completed_at')
               ->distinct();
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('activity_flows.name')
         ->select('activity_flows.*');
         
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
         'responsible_user_id' => 'required|exists:users,id',
         'activity_flow_template_id' => 'required|exists:activity_flow_templates,id',
      ];
   }  

   /**
    * Get the template items
    */
   public function int_activity_flow_template_items() : HasMany
   {
      return $this->hasMany(ActivityFlowTemplateItem::class, 'activity_flow_template_id', 'activity_flow_template_id');
   }   

   /**
    * Get the activity items
    */
   public function int_activities() : HasMany
   {
      return $this->hasMany(Activity::class, 'activity_flow_id');
   }   

   /**
    * Get the pending activities
    */
   public function int_pending_activities() : HasMany
   {
      return $this->hasMany(PendingActivity::class, 'activity_flow_id');
   }   
   
}

