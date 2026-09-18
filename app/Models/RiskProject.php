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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\HasTags;
use App\Traits\HasMessages;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\JoinClause;
use App\Models\Concerns\DefersRelationAttributeSync;

class RiskProject extends Model
{
   use DefersRelationAttributeSync, HasTags, HasMessages;

   public static function getPrettyName($plural = false){ if($plural) { return __("Risk projects"); } else { return __("Risk project"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      if(null != $department)
         return [];
     
     // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', RiskProject::class))
         return [];
      
      $count = RiskProject::whereNull('responsible_user_id')->count();
      if(!$personalOnly && ($count > 0))
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => RiskProject::getPrettyName($count > 1).' '.__("without assignment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/riskprojects') : null];
      
      if($user)
      {
         $count = RiskProject::where('responsible_user_id', $user->id)->whereNull('archived_at')->count();
         if($count > 0)
            $retval[] = ['level' => 'info', 'count' => $count, 'text' => RiskProject::getPrettyName($count > 1).' '.__("ongoing"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/riskprojects') : null];
      }

      // Get number of ongoing projects that have risks that has not been approved
      $rpsPendingAssessment = $user ? Risk::leftJoin('risk_projects', 'risk_projects.id', '=', 'risks.risk_project_id')->whereNotNull('risks.risk_project_id')->where('risk_projects.responsible_user_id', $user->id)->whereNull('risks.assessed_at')->pluck('risks.risk_project_id')->unique()->count() : Risk::whereNotNull('risk_project_id')->whereNull('assessed_at')->pluck('risks.risk_project_id')->unique()->count();
      if($rpsPendingAssessment)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $rpsPendingAssessment, 'text' => RiskProject::getPrettyName($count > 1).' '.__("with pending risk assessments"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/riskprojects') : null];

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
      
      static::creating(function ($model) {
         $model->start_date = date("Y-m-d");
      });
      
      static::created(function ($model) {
         // If based on template, create risks
         if(null != $model->risk_project_type_id)
         {
            foreach(RiskProjectTypeRiskTemplate::where('risk_project_type_id', $model->risk_project_type_id)->get() as $templateObj)
            {
               $risk = new Risk;
               $risk->name = $templateObj->name;
               $risk->department_id = $model->department_id;
               $risk->scenariodescription = $templateObj->scenariodescription;
               $risk->consequencedescription = $templateObj->consequencedescription;
               $risk->created_at = date("Y-m-d H:i:s");
               $risk->updated_at = $risk->created_at;
               $risk->probability_id = $templateObj->probability_id;
               $risk->consequence_id = $templateObj->consequence_id;
               $risk->risk_project_id = $model->id;
               $risk->riskowner_id = $model->responsible_user_id;
               $risk->save();

               // Create control mappings

               $risk->int_controls()->sync($templateObj->int_controls()->pluck('controls.id')->toArray());
            }
         }
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'users',
      'riskcount',
      'riskactioncount',
      'riskdistribution',
      'maxrisklevel',
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
         return ['icon' => 'inventory_2', 'level' => 'info', 'text' => ''];

      // Derive status from risks
      $maxlevel = 'info';
      foreach($this->int_risks as $risk)
      {
         $riskstatus = $risk->status;
         if($riskstatus)
         {
            if($riskstatus['level'] == 'danger')
            {
               $maxlevel = 'danger';
               break;
            }
            elseif($riskstatus['level'] == 'warning')
            {
               $maxlevel = 'warning';
            }
         }
      }
      if($maxlevel != 'info')
         return ['icon' => 'error', 'level' => 'danger', 'text' => __("There are risks that needs attention")];

      return ['icon' => 'pending_actions', 'level' => 'info', 'text' => ''];
   }

   public function getUsersAttribute()
   {
      $retval = [];
      foreach($this->int_users as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function setUsersAttribute($value)
   {
      $this->syncRelationAttribute('users', fn ($model) => $model->int_users()->sync($value));
   }

   public function getRiskcountAttribute()
   {
      return $this->int_risks()->count();
   }

   public function getRiskdistributionAttribute(){
      return Cache::rememberForever('RiskProject.riskdistribution.'.$this->id, function() {
         $riskdistribution = [];

         foreach ($this
                     ->int_risks()->whereNull('replacedby_id')->whereNotNull('assessed_at')
                     ->join('risk_level_mappings', function (JoinClause $join) {
                        $join
                           ->on('risk_level_mappings.probability_level_id', '=', 'risks.probability_id')
                           ->on('risk_level_mappings.consequence_level_id', '=', 'risks.consequence_id');
                     })
                     ->join('risk_levels', 'risk_levels.id', '=', 'risk_level_mappings.risk_level_id')
                     ->groupBy(['risk_level_mappings.probability_level_id', 'risk_level_mappings.consequence_level_id', 'risk_levels.id', 'risk_levels.ordinal', 'risk_levels.color', 'risk_levels.name'])
                     ->selectRaw('risk_level_mappings.probability_level_id, risk_level_mappings.consequence_level_id,risk_levels.id AS risk_level_id,risk_levels.ordinal,risk_levels.color,risk_levels.name AS risk_level_name,count(*) as riskcount')
                     ->get() as $obj)
            $riskdistribution[$obj->probability_level_id][$obj->consequence_level_id] = ['risk_level_id' => $obj->risk_level_id, 'count' => $obj->riskcount, 'color' => $obj->color, 'name' => $obj->risk_level_name, 'ordinal' => $obj->ordinal];

         return $riskdistribution;
      });
   }

   public function getMaxrisklevelAttribute(){
      $highestRisk = $this
                        ->int_risks()->whereNull('replacedby_id')->whereNotNull('assessed_at')
                        ->join('risk_level_mappings', function (JoinClause $join) {
                           $join
                              ->on('risk_level_mappings.probability_level_id', '=', 'risks.probability_id')
                              ->on('risk_level_mappings.consequence_level_id', '=', 'risks.consequence_id');
                        })
                        ->join('risk_levels', 'risk_levels.id', '=', 'risk_level_mappings.risk_level_id')
                        ->orderBy('risk_levels.ordinal', 'desc')
                        ->select('risk_levels.*')
                        ->first();

      if(null == $highestRisk)
         return null;

      return array(
         'id' => $highestRisk->id,
         'name' => $highestRisk->name,
         'color' => $highestRisk->color,
         'ordinal' => $highestRisk->ordinal,
      );
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'department_id',
      'scopedescription',
      'purposedescription',
      'responsible_user_id',
      'created_at',
      'updated_at',
      'archived_at',
      'start_date',
      'end_date',
      'users',
      'risk_project_type_id',
      'status',
      'riskcount',
      'riskdistribution',
      'maxrisklevel',
   ];

   
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'department_id',
      'scopedescription',
      'purposedescription',
      'responsible_user_id',
      'end_date',
      'users',
      'risk_project_type_id',
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
      'unarchive',
      'detachanddelete',
      'deleteproject'
   ];
   
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('scopedescription', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('purposedescription', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->where(function (Builder $query) use($user) {
            // Authorization
            if((null != $user) &&
               !$user->hasAnyPermission(['riskadministrator.edit']))
            {
               $userprojs = DB::table('risk_project_user')->where('user_id', $user->id)->pluck('risk_project_user.risk_project_id')->toArray();
               $query->where('responsible_user_id', $user->id)
               ->orWhereIn('risk_projects.id', $userprojs);
            }
         })
         ->when((0 == request()->input('showarchived', 0)), function (Builder $query) use($user) { 
            $query->whereNull('archived_at');
         })
         ->when((1 == intval(request()->input('showmyonly', 0))), function (Builder $query) {
            $query->where('responsible_user_id',auth()->user()->id);
         })
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
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
         'department_id' => 'nullable|exists:App\Models\Department,id',
         'responsible_user_id' => 'nullable|exists:App\Models\User,id',
         'risk_project_type_id' => 'nullable|exists:App\Models\RiskProjectType,id',
      ];
   }

    /**
     * Get the users
     */
   public function int_users() : BelongsToMany
   {
       return $this->belongsToMany(User::class);
   }   
   
    /**
     * Get the risks
     */
   public function int_risks() : HasMany
   {
       return $this->hasMany(Risk::class, 'risk_project_id');
   }   
   
   /**
    * Archive
    */
   public function archive()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);
      
      // Ensure project is not already archived
      if(null != $this->archived_at)
         abort(400, __("This project is already archived"));
      
      // Ensure all risks are assessed and that there are no remaining activities to be performed
      foreach($this->int_risks as $risk)
      {
         if(null == $risk->assessed_at)
            abort(400, __("There are risks that has not been assessed"));
         
         if($risk->int_control_actions()->whereNull('finished_at')->exists())
            abort(400, __("There are risks with un-finished control activities"));
      }
      
      $this->archived_at = date("Y-m-d H:i:s");
      $this->save();
   }

   /**
    * Un-archive
    */
   public function unarchive()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);
      
      // Ensure project is archived
      if(null == $this->archived_at)
         abort(400, __("This project is not archived"));
      
      $this->archived_at = null;
      $this->save();
   }

   /**
    * Delete project
    */
   public function deleteproject()
   {
      // Ensure correct authorization
      if(request()->user()->hasAnyPermission(['riskadministrator.edit']) ||
         request()->user()->can('update', $this))
      
      $this->delete();
   }
   
   /**
    * Detach risks and delete project
    */
   public function detachanddelete()
   {
      // Ensure correct authorization
      if(request()->user()->hasAnyPermission(['riskadministrator.edit']) ||
         request()->user()->can('update', $this))
      
      // Detach risks
      foreach($this->int_risks as $risk)
      {
         // Add note
         ActivityLog::addMessage(__("This risk was created in risk project")." ".$this->name." ".__("but was detached from the project prior to project deletion"), $risk);
         $risk->risk_project_id = null;
         $risk->save();
      }
      
      $this->delete();
   }

}
