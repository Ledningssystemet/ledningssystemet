<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ActivityFlow extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      return $retval;
   }

    use HasFactory;
    use HasStatus;

    protected $table = 'activity_flows';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'activity_flow_template_id', 'started_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['required', 'integer', 'min:0', 'exists:users,id'],
            'activity_flow_template_id' => ['required', 'integer', 'min:0', 'exists:activity_flow_templates,id'],
            'started_at' => ['nullable', 'date'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
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
        static::created(function (self $model): void {
            $templateItems = ActivityFlowTemplateItem::query()
                ->where('activity_flow_template_id', $model->activity_flow_template_id)
                ->where('type', 'item')
                ->orderBy('ordinal')
                ->get();

            if ($templateItems->isEmpty()) {
                return;
            }

            $startedAt = $model->started_at instanceof Carbon
                ? $model->started_at->copy()->startOfDay()
                : now()->startOfDay();

            foreach ($templateItems as $templateItem) {
                Activity::query()->create([
                    'name' => $templateItem->name,
                    'description' => $templateItem->description ?? $templateItem->name,
                    'due' => $startedAt->copy()->addDays((int) $templateItem->dueoffsetdays)->toDateString(),
                    'intervalnum' => 0,
                    'intervaltype' => null,
                    'completed_at' => null,
                    'responsible_user_id' => $model->responsible_user_id,
                    'activity_flow_id' => $model->id,
                    'activity_flow_template_item_id' => $templateItem->id,
                ]);
            }
        });

        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Activity Flows' : 'Activity Flow';
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_activity_flow_template(): BelongsTo
    {
        return $this->belongsTo(ActivityFlowTemplate::class, 'activity_flow_template_id');
    }

    public function int_activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'activity_flow_id', 'id');
    }

    public function int_pending_activities(): HasMany
    {
        return $this->hasMany(PendingActivity::class, 'activity_flow_id', 'id');
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
      if($this->int_activities()->whereNull('completed_at')->exists())
         return $this->defaultStatus('warning', __("There are activities pending in this flow"));

      return $this->defaultStatus('success', __("All activities are finished"));
   }

}

