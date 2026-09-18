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


class ActivityFlowTemplate extends Model
{
      
   public static function getPrettyName($plural = false){ if($plural) { return __("Activity flow templates"); } else { return __("Activity flow template"); }}

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

      static::saving(function ($model) {
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
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
      'user_instantiatable',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'user_instantiatable',
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
      'start',
   ];
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::
         where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when(!auth()->user()->hasAnyPermission(['managementtools.edit']), function (Builder $query) {
            $query->where('user_instantiatable', true);
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
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
         'name' => ['required'],
         'user_instantiatable' => 'numeric|required|min:0|max:1',
      ];
   }  

   /**
    * Get the items
    */
   public function int_activity_flow_template_items() : HasMany
   {
      return $this->hasMany(ActivityFlowTemplateItem::class, 'activity_flow_template_id');
   }   

   /**
    * Start new activity flow
    */
   public function start(){
      // Ensure user are allowed to start flow
      if(!$this->user_instantiatable && !auth()->user()->hasAnyPermission(['managementtools.edit']))
         abort(403);
      
      DB::transaction(function(){
         // Create activity flow
         $flow = new ActivityFlow();
         $flow->activity_flow_template_id = $this->id;
         $flow->name = request()->input('name');
         $flow->description = request()->input('description');
         $flow->responsible_user_id = auth()->user()->id;
         $flow->started_at = date("Y-m-d H:i:s");
         $flow->save();
         
         // Create all activities
         $preceedingid = null;
         $preceedingisactivity = false;
         foreach($this->int_activity_flow_template_items()->where('type', 'item')->orderBy('ordinal')->get() as $item)
         {
            if('item' != $item->type)
               continue;
            
            $act = null;
            
            if((null != $preceedingid) && $item->waitforpreceeding)
            {
               $preceedingid = DB::table('pending_activities')->insertGetId([
                  'description' => request()->input('itemdescription')[$item->id],
                  'dependant_activity_id' => $preceedingisactivity ? $preceedingid : null,
                  'dependant_pending_activity_id' => $preceedingisactivity ? null : $preceedingid,
                  'activity_flow_template_item_id' => $item->id,
                  'activity_flow_id' => $flow->id,
                  'responsible_user_id' => request()->input('itemresponsible_user_id')[$item->id],
               ]);
               $preceedingisactivity = false;
            }
            else
            {
               $act = new Activity();
               $act->name = $item->name;
               $act->description = request()->input('itemdescription')[$item->id];
               $act->responsible_user_id = request()->input('itemresponsible_user_id')[$item->id];
               $act->activity_flow_id = $flow->id;
               $act->activity_flow_template_item_id = $item->id;
               $act->responsible_user_id = request()->input('itemresponsible_user_id')[$item->id];
               $act->due = request()->input('itemdue')[$item->id];
               $act->save();
               $preceedingid = $act->id;
               $preceedingisactivity = true;
            }
         }
      });
      return true;
   }
}

