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
use App\Traits\HasNotifications;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Incident extends Model
{
   use HasNotifications, HasCustomProperties;

   public static function getPrettyName($plural = false){ if($plural) { return __("Incidents"); } else { return __("Incident"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      
      if(null != $department)
         return [];
      
      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', Incident::class))
         return [];
      
      $count = $user ? Incident::where('responsible_user_id', $user->id)->whereNull('finished_at')->count() : Incident::whereNull('finished_at')->count();
      if($count)
         $retval[] = ['level' => 'warning', 'count' => $count, 'text' => __("Ongoing"). ' ' . strtolower(Incident::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/incidents') : null];

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
         
         // Prevent changing a closed incident
         if(null != $model->getOriginal('finished_at'))
            abort(403, __("You may not change a closed incident"));
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'actioncount',
      'logcount',
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
      if(!$this->finished_at)
         return ['icon' => 'report', 'level' => 'warning', 'text' => __("This incident is not yet handled")];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
      
   }

   public function getActioncountAttribute()
   {
      return $this->int_control_actions()->count();
   }

   public function getLogcountAttribute()
   {
      return $this->int_logs()->count();
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
      'started_at',
      'finished_at',
      'eventdescription',
      'participants',
      'retrospective',
      'responsible_user_id',
      'created_at',
      'updated_at',
      'status',
      'actioncount',
      'logcount',

   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'eventdescription',
      'participants',
      'retrospective',
      'started_at',
      'responsible_user_id',
      'finished_at',
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
      $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%');
         })
         ->where(function (Builder $query) { 
            if((0 == request()->input('showongoing', 0)) &&
               (1 == request()->input('showfinished', 0)))
               $query->whereNotNull('finished_at');
            else if((1 == request()->input('showongoing', 0)) &&
               (0 == request()->input('showfinished', 0)))
               $query->whereNull('finished_at');
            else if((0 == request()->input('showongoing', 0)) &&
               (0 == request()->input('showfinished', 0)))
               $query->where('id',0);
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
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
         'name' => ['required'],
         'responsible_user_id' => 'nullable|exists:App\Models\User,id',
      ];
   }
   
   public function int_responsible_user() : BelongsTo
   {
      return $this->belongsTo(User::class, 'responsible_user_id');
   }

   /**
    * Get the control actions associated with the incident
    */
   public function int_control_actions() : BelongsToMany
   {
      return $this->belongsToMany(ControlAction::class, 'control_action_mappings', 'incident_id', 'control_action_id');
   }

   public function int_logs() : HasMany
   {
      return $this->hasMany(IncidentLog::class, 'incident_id');
   }
}
