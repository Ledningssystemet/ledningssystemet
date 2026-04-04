<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class PendingActivity extends Model
{
    use HasFactory;

    protected $table = 'pending_activities';

    protected $fillable = ['description', 'dependant_activity_id', 'activity_flow_id', 'activity_flow_template_item_id', 'responsible_user_id', 'dependant_pending_activity_id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'description' => ['nullable', 'string'],
            'dependant_activity_id' => ['nullable', 'integer', 'min:0', 'exists:activities,id'],
            'activity_flow_id' => ['required', 'integer', 'min:0', 'exists:activity_flows,id'],
            'activity_flow_template_item_id' => ['required', 'integer', 'min:0', 'exists:activity_flow_template_items,id'],
            'responsible_user_id' => ['required', 'integer', 'min:0', 'exists:users,id'],
            'dependant_pending_activity_id' => ['nullable', 'integer', 'min:0', 'exists:pending_activities,id'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'description',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Pending Activities' : 'Pending Activity';
    }

    public function int_dependant_pending_activity(): BelongsTo
    {
        return $this->belongsTo(PendingActivity::class, 'dependant_pending_activity_id');
    }

    public function int_activity_flow(): BelongsTo
    {
        return $this->belongsTo(ActivityFlow::class, 'activity_flow_id');
    }

    public function int_activity_flow_template_item(): BelongsTo
    {
        return $this->belongsTo(ActivityFlowTemplateItem::class, 'activity_flow_template_item_id');
    }

    public function int_dependant_activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'dependant_activity_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_pending_activities(): HasMany
    {
        return $this->hasMany(PendingActivity::class, 'dependant_pending_activity_id', 'id');
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
}
