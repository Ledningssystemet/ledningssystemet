<?php

namespace Ledningssystemet\Ledningssystemet\Models;

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
use Ledningssystemet\Ledningssystemet\Traits\HasTags;
use Ledningssystemet\Ledningssystemet\Traits\HasMessages;
use Ledningssystemet\Ledningssystemet\Http\Controllers\UserNotificationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\JoinClause;
use Ledningssystemet\Ledningssystemet\Models\Concerns\DefersRelationAttributeSync;

class Project extends Model
{
   use DefersRelationAttributeSync, HasTags, HasMessages;

   public static function getPrettyName($plural = false){ if($plural) { return __("Projects"); } else { return __("Project"); }}

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public static function boot()
   {
      parent::boot();
      
      static::creating(function ($model) {
         if (!$model->start_date)
            $model->start_date = date("Y-m-d");
      });
      
      static::created(function ($model) {
         // If based on template, create risks
         if(null != $model->project_type_id)
         {
            foreach(ProjectTypeRiskTemplate::where('project_type_id', $model->project_type_id)->get() as $templateObj)
            {
               $risk = new ProjectRisk;
               $risk->name = $templateObj->name;
               $risk->scenariodescription = $templateObj->scenariodescription;
               $risk->consequencedescription = $templateObj->consequencedescription;
               $risk->created_at = date("Y-m-d H:i:s");
               $risk->updated_at = $risk->created_at;
               $risk->probability_id = $templateObj->probability_id;
               $risk->consequence_id = $templateObj->consequence_id;
               $risk->project_id = $model->id;
               $risk->riskowner_id = $model->responsible_user_id;
               $risk->save();

            }
         }
      });

      static::deleting(function ($model) {
         // Ensure that the project is archived before allowing deletion
         if(null == $model->archived_at)
            abort(400, __("A project must be archived before it can be deleted"));
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'users',
      'responsible_user_name',
      'riskcount',
      'riskdistribution',
      'maxrisklevel',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this), 'write' => $this->canWrite($user), 'manageparticipants' => $this->responsible_user_id == $user->id];
   }

   public function canWrite(User $user): bool
   {
      if ($this->responsible_user_id == $user->id)
         return true;

      return $this->int_users()->where('users.id', $user->id)->wherePivot('readonly', false)->exists();
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
         $retval[] = array('id' => $obj->id, 'name' => $obj->name, 'readonly' => (bool) $obj->pivot->readonly);
      return $retval;
   }

   public function getResponsibleUserNameAttribute(): ?string
   {
      return $this->int_responsible_user?->name;
   }
   
   public function updateparticipants(): void
   {
      if (request()->user()->id != $this->responsible_user_id)
         abort(403);

      $data = Validator::make(request()->all(), [
         'users' => 'present|array',
         'users.*.id' => ['required', 'integer', 'distinct', 'exists:users,id', Rule::notIn([$this->responsible_user_id])],
         'users.*.readonly' => 'required|boolean',
      ])->validate();

      $participants = [];
      foreach ($data['users'] as $participant)
         $participants[$participant['id']] = ['readonly' => (bool) $participant['readonly']];

      DB::transaction(function () use ($participants): void {
         $this->int_users()->sync($participants);
         $this->touch();
         ActivityLog::addMessage(__("Project participants were updated"), $this);
      });
      $this->unsetRelation('int_users');
   }

   public function getRiskcountAttribute()
   {
      return $this->int_risks()->count();
   }

   public function getRiskdistributionAttribute(){
      return Cache::rememberForever('Project.riskdistribution.'.$this->id, function() {
         $riskdistribution = [];

         foreach ($this
                     ->int_risks()->whereNotNull('assessed_at')
                     ->join('risk_level_mappings', function (JoinClause $join) {
                        $join
                           ->on('risk_level_mappings.probability_level_id', '=', 'project_risks.probability_id')
                           ->on('risk_level_mappings.consequence_level_id', '=', 'project_risks.consequence_id');
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
                        ->int_risks()->whereNotNull('assessed_at')
                        ->join('risk_level_mappings', function (JoinClause $join) {
                           $join
                              ->on('risk_level_mappings.probability_level_id', '=', 'project_risks.probability_id')
                              ->on('risk_level_mappings.consequence_level_id', '=', 'project_risks.consequence_id');
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
      'description',
      'responsible_user_id',
      'responsible_user_name',
      'created_at',
      'updated_at',
      'archived_at',
      'start_date',
      'end_date',
      'users',
      'project_type_id',
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
      'description',
      'responsible_user_id',
      'start_date',
      'end_date',
      'project_type_id',
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
      'updateparticipants',
      'deleteproject',
      'meetingagenda',
   ];
   

   public function scopeVisibleTo(Builder $query, User $user): Builder
   {
      $query->with('int_responsible_user:id,name');

      if ($user->hasAnyPermission(['superadmin.edit'])) {
         return $query;
      }

      return $query->where(function (Builder $query) use ($user) {
         $query->where('responsible_user_id', $user->id)
            ->orWhereHas('int_users', function (Builder $query) use ($user) {
               $query->where('users.id', $user->id);
            });
      });
   }

   public function getAuthorizationFields(): array
   {
      return ['id', 'responsible_user_id'];
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
         'description' => 'nullable|string',
         'responsible_user_id' => 'nullable|exists:Ledningssystemet\Ledningssystemet\Models\User,id',
         'project_type_id' => 'nullable|exists:Ledningssystemet\Ledningssystemet\Models\ProjectType,id',
         'start_date' => 'required|date',
         'end_date' => 'nullable|date|after_or_equal:start_date',
      ];
   }

    /**
     * Get the users
     */
   public function int_users() : BelongsToMany
   {
       return $this->belongsToMany(User::class)->withPivot('readonly');
   }   

   public function int_responsible_user() : BelongsTo
   {
       return $this->belongsTo(User::class, 'responsible_user_id');
   }
   
    /**
     * Get the risks associated with this project
     */
   public function int_risks() : HasMany
   {
       return $this->hasMany(ProjectRisk::class, 'project_id');
   }   

   public function int_activities() : HasMany
   {
       return $this->hasMany(ProjectActivity::class, 'project_id');
   }

   public function int_work_packages() : HasMany
   {
       return $this->hasMany(ProjectWorkPackage::class, 'project_id');
   }

   public function int_meetings() : HasMany
   {
       return $this->hasMany(ProjectMeeting::class, 'project_id');
   }

   public function meetingagenda(): array
   {
       abort_unless(auth()->user()->can('view', $this), 403);
       $data = Validator::make(request()->all(), [
           'date' => 'required|date_format:Y-m-d',
           'project_meeting_agenda_template_id' => 'nullable|integer|min:1',
       ])->validate();

       return \Ledningssystemet\Ledningssystemet\Support\ProjectMeetingAgenda::forProject($this, $data['date'], isset($data['project_meeting_agenda_template_id']) ? (int) $data['project_meeting_agenda_template_id'] : null);
   }

   public function int_project_type() : BelongsTo
   {
       return $this->belongsTo(ProjectType::class, 'project_type_id');
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
      }
      if ($this->int_activities()->whereNull('completed_at')->exists())
         abort(400, __("There are risks with un-finished control activities"));
      
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
}
