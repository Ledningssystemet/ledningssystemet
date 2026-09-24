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

class UserNotificationChannel extends Model
{
       
   public static function getPrettyName($plural = false){ if($plural) { return __("User notification channels"); } else { return __("User notification channel"); }}

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
      'channelscopes',
      'channelevents',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getChannelscopesAttribute()
   {
      return json_decode($this->scopes);
   }

   public function setChannelscopesAttribute($value)
   {
      $objs = [];
      foreach(UserNotificationChannel::availableScopes() as $scope)
      {
         if($value && in_array($scope['key'], $value))
            $objs[] = $scope['key'];
      }
      $this->scopes = json_encode($objs);
   }

   public function getChanneleventsAttribute()
   {
      return json_decode($this->events);
   }

   public function setChanneleventsAttribute($value)
   {
      $objs = [];
      foreach(UserNotificationChannel::availableEvents() as $scope)
      {
         if($value && in_array($scope['key'], $value))
            $objs[] = $scope['key'];
      }
      $this->events = json_encode($objs);
   }
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'user_id',
      'name',
      'webhookurl',
      'email',
      'ignoremine',
      'created_at',
      'updated_at',
      'channelscopes',
      'channelevents',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'user_id',
      'name',
      'webhookurl',
      'email',
      'ignoremine',
      'channelscopes',
      'channelevents',
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
      $userid = request()->input('user_id', null);
      
      if(($userid != $user->id) && !$user->hasAnyPermission(['systemadministrator.edit']))
         abort(403);
         
      $returnCollection = (__CLASS__)::when(null != $userid , function (Builder $query) {
            $query->where('user_id', request()->input('user_id'));
         })
         ->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%');
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
         'name' => 'required',
         'webhookurl' => 'nullable|url:https,http',
         'user_id' => 'required|exists:users,id',
      ];
   }
     
   /**
    * Get the user associated with the object
    */
    public function int_user() : BelongsTo
    {
       return $this->belongsTo(User::class, 'user_id');
    }       
    
    /**
     * Get available scopes
     */
   public static function availableScopes(){
      return [
         array('key' => 'Activity', 'text' => __('Activity')),
         array('key' => 'Asset', 'text' => __('Asset')),
         array('key' => 'ComplianceEvaluation', 'text' => __('Compliance evaluation')),
         array('key' => 'Control', 'text' => __('Control')),
         array('key' => 'ControlAction', 'text' => __('Control action')),
         array('key' => 'Finding', 'text' => __('Finding')),
         array('key' => 'Incident', 'text' => __('Incident')),
         array('key' => 'InformationType', 'text' => __('Information type')),
         array('key' => 'Process', 'text' => __('Process')),
         array('key' => 'ProcessActivity', 'text' => __('Task')),
         array('key' => 'Requirement', 'text' => __('Requirement')),
         array('key' => 'RequirementSource', 'text' => __('Requirement source')),
         array('key' => 'Risk', 'text' => __('Risk')),
         array('key' => 'Supplier', 'text' => __('Supplier')),
      ];
   }

    /**
     * Get available events
     */
   public static function availableEvents(){
      return [
         array('key' => 'created', 'text' => __('Created')),
         array('key' => 'updated', 'text' => __('Updated')),
         array('key' => 'myupdated', 'text' => __('Mine is updated')),
         array('key' => 'assignedtome', 'text' => __('Assigned to me')),
         array('key' => 'deleted', 'text' => __('Deleted')),
      ];
   }
}
