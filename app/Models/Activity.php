<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Activity extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      
      if(null != $department)
         return [];
      
      $count = Activity::whereNull('responsible_user_id')->count();
      if(!$personalOnly && (null != auth()->user()) && auth()->user()->haveAnyAccessRights(['managementtools.edit']) && ($count > 0))
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

    use HasFactory;
    use HasStatus;

    protected $table = 'activities';

    protected $fillable = ['name', 'description', 'due', 'intervalnum', 'intervaltype', 'completed_at', 'responsible_user_id', 'activity_flow_id', 'activity_flow_template_item_id'];

    protected function casts(): array
    {
        return [
            'due' => 'date',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'due' => ['required', 'date'],
            'intervalnum' => ['required', 'integer', 'min:0'],
            'intervaltype' => ['nullable', 'string', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'activity_flow_id' => ['nullable', 'integer', 'min:0', 'exists:activity_flows,id'],
            'activity_flow_template_item_id' => ['nullable', 'integer', 'min:0', 'exists:activity_flow_template_items,id'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'intervaltype',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
        ];
    }

    public static function applyCrudIndexFilters(mixed $query, Request $request): void
    {
        $user = $request->user();

        if ($user && ! $user->haveAnyAccessRights(['managementtools.edit'])) {
            $query->where('responsible_user_id', $user->id);
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Activities' : 'Activity';
    }

    public function int_activity_flow(): BelongsTo
    {
        return $this->belongsTo(ActivityFlow::class, 'activity_flow_id');
    }

    public function int_activity_flow_template_item(): BelongsTo
    {
        return $this->belongsTo(ActivityFlowTemplateItem::class, 'activity_flow_template_item_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_pending_activities(): HasMany
    {
        return $this->hasMany(PendingActivity::class, 'dependant_activity_id', 'id');
    }

    public function int_custom_property_object_as_object(): MorphMany
    {
        return $this->morphMany(CustomPropertyObject::class, 'object', 'object_type', 'object_id');
    }

    public function int_files_as_object(): MorphMany
    {
        return $this->morphMany(File::class, 'object', 'object_type', 'object_id');
    }

    public function int_findings_as_context(): MorphMany
    {
        return $this->morphMany(Finding::class, 'context', 'context_type', 'context_id');
    }

    public function int_object_histories_as_object(): MorphMany
    {
        return $this->morphMany(ObjectHistory::class, 'object', 'object_type', 'object_id');
    }

    public function int_object_messages_as_object(): MorphMany
    {
        return $this->morphMany(ObjectMessage::class, 'object', 'object_type', 'object_id');
    }

    public function int_object_properties_as_object_properties(): MorphMany
    {
        return $this->morphMany(ObjectProperty::class, 'object_properties', 'object_properties_type', 'object_properties_id');
    }

    public function int_object_tags_as_object_tags(): MorphMany
    {
        return $this->morphMany(ObjectTag::class, 'object_tags', 'object_tags_type', 'object_tags_id');
    }

    public function int_personal_access_tokens_as_tokenable(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable', 'tokenable_type', 'tokenable_id');
    }

    public function int_risks_as_context(): MorphMany
    {
        return $this->morphMany(Risk::class, 'context', 'context_type', 'context_id');
    }

    public function int_vector_embeddings_as_embeddable(): MorphMany
    {
        return $this->morphMany(VectorEmbedding::class, 'embeddable', 'embeddable_type', 'embeddable_id');
    }

    protected function resolveStatus(): array
   {
      if($this->completed_at)
      return $this->defaultStatus('success', '');
         
      if(!$this->responsible_user_id)
         return $this->defaultStatus('danger', __("A responsible user has not been assigned"));
      
      if((strtotime($this->due) < time()) && (null != request()->user()) && ($this->responsible_user_id == request()->user()->id))
         return $this->defaultStatus('danger', __("Activitiy is overdue"));
         
      if(strtotime($this->due) < time())
         return $this->defaultStatus('warning', __("Activitiy is overdue"));
      
      return $this->defaultStatus('success', '');
      
   }

}

