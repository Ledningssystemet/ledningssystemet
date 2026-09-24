<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
use Illuminate\Database\Eloquent\Casts\Attribute;

class ControlAction extends Model
{
   use HasTags, HasMessages, HasNotifications;

   public static function getPrettyName($plural = false){ if($plural) { return __("Control actions"); } else { return __("Control action"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      
      if(null != $department)
         return [];
      
      $count = ($user == null) ? ControlAction::whereNull('finished_at')->where('due', '<', date("Y-m-d"))->count() : ControlAction::where('responsible_id', $user->id)->whereNull('finished_at')->where('due', '<', date("Y-m-d"))->count();
      if($count)
         $retval[] = ['level' => (null == $user) ? 'warning' : 'danger', 'count' => $count, 'text' => __("Overdue"). ' ' . strtolower(ControlAction::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/controlactions') : null, 'personal' => ($user != null)];

      $count = ($user == null) ? ControlAction::whereNull('finished_at')->where('due', '>=', date("Y-m-d"))->count() : ControlAction::where('responsible_id', $user->id)->whereNull('finished_at')->where('due', '>=', date("Y-m-d"))->count();
      if($count)
         $retval[] = ['level' => 'info', 'count' => $count, 'text' => __("Planned"). ' ' . strtolower(ControlAction::getPrettyName($count > 1)), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/controlactions') : null, 'personal' => ($user != null)];
      
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

         // Check if we are trying to associate with an existing control. If so, create a control mapping rather than a new control action
         if(0 < request()->input('existing_control_action_id', 0))
         {
            $existing = ControlAction::find(request()->input('existing_control_action_id'));

            if(null == $existing)
               abort(400, __('The specified existing control action does not exist'));

            // Get original object
            if(0 < request()->input('risk_id', 0)) {
               $existing->int_risks()->syncWithoutDetaching(request()->input('risk_id'));
               foreach($existing->int_risks as $risk) {
                  $risk->int_controls()->syncWithoutDetaching($existing->control_id);
                }
            }

            if(0 < request()->input('incident_id', 0))
               $existing->int_incidents()->syncWithoutDetaching(request()->input('incident_id'));

            if(0 < request()->input('finding_id', 0))
               $existing->int_findings()->syncWithoutDetaching(request()->input('finding_id'));

            if(0 < request()->input('objective_id', 0))
               $existing->int_objectives()->syncWithoutDetaching(request()->input('objective_id'));

            return false;
         }



         Validator::make($model->toArray(), $model->getValidationRules())->validate();
         
         if($model->isDirty('finished_at') && $model->finished_at)
         {
            // Ensure current user is the responsible
            if(auth()->user()->id != $model->responsible_id)
               abort(403, 'Only the action responsible can mark implementation as finished');
            
            // Ensure action has not been finished already
            if(null != $model->getOriginal('finished_at'))
               abort(400, 'The action has already been finished');
         }
         else if($model->finished_at)
            abort(400, 'You may not change a finished control action');
         
         // Check if due is changed
         if($model->isDirty('due') && (null == $model->originaldue))
            $model->originaldue = $model->due;
      });

      static::saved(function ($model) {
         // Attach any appointed ids
         if(0 < request()->input('risk_id', 0))
            $model->int_risks()->syncWithoutDetaching(request()->input('risk_id'));

         if(0 < request()->input('incident_id', 0))
            $model->int_incidents()->syncWithoutDetaching(request()->input('incident_id'));

         if(0 < request()->input('finding_id', 0))
            $model->int_findings()->syncWithoutDetaching(request()->input('finding_id'));

         if(0 < request()->input('objective_id', 0))
            $model->int_objectives()->syncWithoutDetaching(request()->input('objective_id'));
      });
      
      // Re-open risk, finding or incident related to this action.
      static::deleting(function ($model) {

         // Check if this is actually a request to delete an action mapping, rather than a real delete of the action itself
         if(0 < request()->input('risk_id', 0))
         {
            // Check if the risk is still open
            $obj = Risk::find(request()->input('risk_id'));

            if((null != $obj) && ((null != $obj->replacedby_id) || (null != $obj->assessed_at)))
               abort(400, 'You may not alter this already closed risk');

            DB::table('control_action_mappings')->where('control_action_id', $model->id)->where('risk_id', request()->input('risk_id'))->delete();

            // Prevent further deletion of the action itself, as we only want to remove the mapping
            return false;
         }

         if(0 < request()->input('incident_id', 0))
         {
            // Check if the incident is still open
            $obj = Incident::find(request()->input('incident_id', 0));

            if((null != $obj) && (null != $obj->finished_at))
               abort(400, 'You may not alter this already closed incident');

            DB::table('control_action_mappings')->where('control_action_id', $model->id)->where('incident_id', request()->input('incident_id'))->delete();

            // Prevent further deletion of the action itself, as we only want to remove the mapping
            return false;
         }

         if(0 < request()->input('finding_id', 0))
         {
            // Check if the finding is still open
            $obj = Finding::find(request()->input('finding_id', 0));

            if((null != $obj) && (null != $obj->finished_at))
               abort(400, 'You may not alter this already closed finding');

            DB::table('control_action_mappings')->where('control_action_id', $model->id)->where('finding_id', request()->input('finding_id'))->delete();

            // Prevent further deletion of the action itself, as we only want to remove the mapping
            return false;
         }

         if(0 < request()->input('objective_id', 0))
         {
            DB::table('control_action_mappings')->where('control_action_id', $model->id)->where('objective_id', request()->input('objective_id'))->delete();

            // Prevent further deletion of the action itself, as we only want to remove the mapping
            return false;
         }

         // Interate over all mappings
         foreach(DB::table('control_action_mappings')->where('control_action_id', $model->id)->get() as $mapping)
         {
            // Re-open risk
            $obj = Risk::find($mapping->risk_id);
            if((null != $obj) && (null == $obj->replacedby_id))
            {
               // Only care if the risk has not yet been assessed
               if(null != $obj->assessed_at)
               {
                  DB::table('risks')->where('id', $obj->id)->update(['assessed_at' => null]);
                  ActivityLog::addMessage(__("This was re-opened for assessment because the action").' '.$model->name.' '.__("was deleted"), $obj);
               }
            }

            // Re-open finding
            $obj = Finding::find($mapping->finding_id);
            if(null != $obj)
            {
               // Only care if the finding has not yet been assessed
               if(null != $obj->finished_at)
               {
                  ActivityLog::addMessage(__("This was re-opened for assessment because the action").' '.$model->name.' '.__("was deleted"), $obj);
                  DB::table('findings')->where('id', $obj->id)->update(['finished_at' => null]);
               }
            }

            // Re-open incident
            $obj = Incident::find($mapping->incident_id);
            if(null != $obj)
            {
               // Only care if the incident has not yet been assessed
               if(null != $obj->finished_at)
               {
                  ActivityLog::addMessage(__("This was re-opened for assessment because the action").' '.$model->name.' '.__("was deleted"), $obj);
                  DB::table('incidents')->where('id', $obj->id)->update(['finished_at' => null]);
               }
            }

            // Log removal in objectives
            $obj = Objective::find($mapping->objective_id);
            if(null != $obj)
            {
               ActivityLog::addMessage(__("The action").' '.$model->name.' '.__("was deleted"), $obj);
               $obj->update();
            }
         }
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'origin',
      'status',
      'created_by_name',
      'tags',
      'messagecount',
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
      if(!$this->finished_at && (strtotime($this->due) > time()))
         return ['icon' => 'report', 'level' => 'info', 'text' => ''];
      
      if(!$this->finished_at && (null != request()->user()) && ($this->responsible_id == request()->user()->id))
         return ['icon' => 'report', 'level' => 'danger', 'text' => __("This action is overdue")];
         
      if(!$this->finished_at)
         return ['icon' => 'report', 'level' => 'warning', 'text' => __("This action is overdue")];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }
   
   public function getOriginAttribute()
   {
      $retval = "";

      // Fast path: use eager-loaded relations when available (avoids mappings query + per-mapping finds)
      if ($this->relationLoaded('int_risks') &&
          $this->relationLoaded('int_findings') &&
          $this->relationLoaded('int_incidents') &&
          $this->relationLoaded('int_objectives')) {

         foreach ($this->int_risks->whereNull('replacedby_id') as $risk)
            $retval .= (("" != $retval) ? "\r\n" : "") . __("RISK") . '-' . $risk->id . ' (' . $risk->name_pretty . ')';

         foreach ($this->int_incidents as $incident)
            $retval .= (("" != $retval) ? "\r\n" : "") . __("INCIDENT") . '-' . $incident->id . ' (' . $incident->name . ')';

         foreach ($this->int_findings as $finding)
            $retval .= (("" != $retval) ? "\r\n" : "") . __("FINDING") . '-' . $finding->id . ' (' . $finding->name . ')';

         foreach ($this->int_objectives as $objective)
            $retval .= (("" != $retval) ? "\r\n" : "") . __("OBJECTIVE") . '-' . $objective->id . ' (' . $objective->name . ')';

         return $retval;
      }

      // Fallback: original query-based approach (single-record view, cold cache, etc.)
      // Note: also fixes the original double Risk::find() per mapping
      foreach (DB::table('control_action_mappings')->where('control_action_id', $this->id)->get() as $mapping) {
         if (null != $mapping->risk_id) {
            $risk = Risk::find($mapping->risk_id);
            if (null != $risk && null == $risk->replacedby_id)
               $retval .= (("" != $retval) ? "\r\n" : "") . __("RISK") . '-' . $mapping->risk_id . ' (' . $risk->name_pretty . ')';
         }

         if (null != $mapping->incident_id)
            $retval .= (("" != $retval) ? "\r\n" : "") . __("INCIDENT") . '-' . $mapping->incident_id . ' (' . Incident::find($mapping->incident_id)->name . ')';

         if (null != $mapping->finding_id)
            $retval .= (("" != $retval) ? "\r\n" : "") . __("FINDING") . '-' . $mapping->finding_id . ' (' . Finding::find($mapping->finding_id)->name . ')';

         if (null != $mapping->objective_id)
            $retval .= (("" != $retval) ? "\r\n" : "") . __("OBJECTIVE") . '-' . $mapping->objective_id . ' (' . Objective::find($mapping->objective_id)->name . ')';
      }

      return $retval;
   }
   
   public function getCreatedByNameAttribute()
   {
      return Cache::rememberForever('ControlAction.getCreatedByNameAttribute.'.$this->id, function(){
         $firstEvent = ActivityLog::query()
            ->where('subject_type', 'App\\Models\\ControlAction')
            ->where('subject_id', $this->id)
            ->where('event', 'created')
            ->first();
         
         return (null == $firstEvent) ? "" : $firstEvent->created_by;
      });
   }
   
   public function getTagsAttribute()
   {
      if ($this->relationLoaded('tags')) {
         return $this->getRelation('tags');
      }

      return Cache::rememberForever('ControlAction.getTagsAttribute.' . $this->id, function () {
         return $this->tags()->get();
      });
   }
   
   public function getMessagecountAttribute()
   {
      if (array_key_exists('messages_count', $this->attributes)) {
         return intval($this->attributes['messages_count']);
      }

      return $this->messages()->count();
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
      'control_id',
      'responsible_id',
      'due',
      'finished_at',
      'created_at',
      'updated_at',
      'origin',
      'status',
      'created_by_name',
      'tags',
      'messagecount',
      'estimated_cost',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'responsible_id',
      'control_id',
      'due',
      'finished_at',
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
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $scopeUndefined = true;

      if (  (0 < request()->input('risk')) ||
            (0 < request()->input('control')) ||
            (0 < request()->input('finding')) ||
            (0 < request()->input('incident')) ||
            (0 < request()->input('objective_id'))
         )
         $scopeUndefined = false;

      $returnCollection = (__CLASS__)::where(function (Builder $query) {
            // Pending/handled filter?
            if ((1 == request()->input('showhandled', 0)) &&
               (0 == request()->input('showpending', 1)))
               $query->whereNotNull('finished_at');
            else if ((0 == request()->input('showhandled', 0)) &&
               (1 == request()->input('showpending', 1)))
               $query->whereNull('finished_at');
            else if ((0 == request()->input('showhandled', 0)) &&
               (0 == request()->input('showpending', 1)))
               $query->where('control_actions.id', 0);
         })
         ->when((1 == request()->input('showmyonly', 0)) || ($scopeUndefined && !auth()->user()->hasAnyPermission(['allcontrolactions.read'])), function (Builder $query) {
            $query->where('responsible_id', auth()->user()->id);
         })
         ->when(request()->has('risk'), function (Builder $query) {
            if (0 < request()->input('risk'))
               $query->whereHas('int_risks', function (Builder $query) {
                  $query->where('risks.id', request()->input('risk'));
               });
         })
         ->when(request()->has('control'), function (Builder $query) {
            if (0 < request()->input('control'))
               $query->where('control_id', request()->input('control'));
         })
         ->when(request()->has('finding'), function (Builder $query) {
            if (0 < request()->input('finding'))
               $query->whereHas('int_findings', function (Builder $query) {
                  $query->where('findings.id', request()->input('finding'));
               });
         })
         ->when(request()->has('incident'), function (Builder $query) {
            if (0 < request()->input('incident'))
               $query->whereHas('int_incidents', function (Builder $query) {
                  $query->where('incidents.id', request()->input('incident'));
               });
         })
         ->when(request()->has('objective_id'), function (Builder $query) {
            if (0 < request()->input('objective_id'))
               $query->whereHas('int_objectives', function (Builder $query) {
                  $query->where('objectives.id', request()->input('objective_id'));
               });
         })
         ->where(function (Builder $query) {
            $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%' . request()->input('search') . '%')
                  ->orWhere('description', 'LIKE', '%' . request()->input('search') . '%')
                  ->orWhereHas('tags', function (Builder $query) {
                     $query->where('name', 'LIKE', '%' . request()->input('search') . '%');
                  });
            });
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable() . '.id', intval(request()->input('id', 0)));
         })
         ->when(0 < intval(request()->input('responsible_id', 0)), function (Builder $query) {
            $query->where('responsible_id', intval(request()->input('responsible_id', 0)));
         })
         ->when((0 < intval(request()->input('tag_id', 0))), function (Builder $query) {
            $query->whereHas('tags', function (Builder $query) {
               $query->where('tags.id', intval(request()->input('tag_id', 0)));
            });
         })
         // hidechecked: status != 'info' means not finished AND overdue — push to SQL
         ->when(request()->input('hidechecked', 0), function (Builder $query) {
            $query->whereNull('finished_at')
               ->whereRaw('due < NOW()');
         })
         ->select('control_actions.*')
         ->with([
            'tags',
            'int_risks:id,name,replacedby_id,context_type,context_id',
            'int_findings:id,name',
            'int_incidents:id,name',
            'int_objectives:id,name',
         ])
         ->withCount('messages')
         ->orderByRaw('finished_at IS NOT NULL')
         ->orderBy('due');

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
         'name' => [
            'required',
         ],
         'responsible_id' => 'nullable|exists:App\Models\User,id',
         'control_id' => 'required|exists:App\Models\Control,id',
         'estimated_cost' => 'sometimes|nullable|numeric|min:0',
      ];
   }

   public function int_responsible() : BelongsTo
   {
      return $this->belongsTo(User::class, 'responsible_id');
   }
   
   public function int_control() : BelongsTo
   {
      return $this->belongsTo(Control::class, 'control_id');
   }

   public function int_risks() : BelongsToMany
   {
      return $this->belongsToMany(Risk::class, 'control_action_mappings', 'control_action_id', 'risk_id');
   }

   public function int_findings() : BelongsToMany
   {
      return $this->belongsToMany(Finding::class, 'control_action_mappings', 'control_action_id', 'finding_id');
   }

   public function int_incidents() : BelongsToMany
   {
      return $this->belongsToMany(Incident::class, 'control_action_mappings', 'control_action_id', 'incident_id');
   }

   public function int_objectives() : BelongsToMany
   {
      return $this->belongsToMany(Objective::class, 'control_action_mappings', 'control_action_id', 'objective_id');
   }
}
