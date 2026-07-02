<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class ActivityFlowTemplateItem extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      return [];
   }

    use HasFactory;

    protected $table = 'activity_flow_template_items';

    protected $fillable = ['name', 'type', 'ordinal', 'description', 'waitforpreceeding', 'dueoffsetdays', 'activity_flow_template_id'];

    protected function casts(): array
    {
        return [
            'waitforpreceeding' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:header,item'],
            'ordinal' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'waitforpreceeding' => ['required', 'boolean'],
            'dueoffsetdays' => ['required', 'integer', 'min:0'],
            'activity_flow_template_id' => ['required', 'integer', 'min:0', 'exists:activity_flow_templates,id'],
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

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->ordinal !== null) {
                return;
            }

            $lastOrdinal = static::query()
                ->where('activity_flow_template_id', $model->activity_flow_template_id)
                ->max('ordinal');

            $model->ordinal = $lastOrdinal !== null ? ((int) $lastOrdinal + 1) : 0;
        });

        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Activity Flow Template Items' : 'Activity Flow Template Item';
    }

    public function int_activity_flow_template(): BelongsTo
    {
        return $this->belongsTo(ActivityFlowTemplate::class, 'activity_flow_template_id');
    }

    public function int_activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'activity_flow_template_item_id', 'id');
    }

    public function int_pending_activities(): HasMany
    {
        return $this->hasMany(PendingActivity::class, 'activity_flow_template_item_id', 'id');
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
