<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class Requirement extends Model
{
    use HasFactory;
    use HasStatus;

    protected $table = 'requirements';

    protected $fillable = ['requirement_source_id', 'iscontrol', 'applicable', 'name', 'reference', 'ordinal', 'description', 'governance'];

    protected function casts(): array
    {
        return [
            'iscontrol' => 'boolean',
            'applicable' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'requirement_source_id' => ['required', 'integer', 'min:0', 'exists:requirement_sources,id'],
            'iscontrol' => ['required', 'boolean'],
            'applicable' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:100'],
            'reference' => ['required', 'string', 'max:20'],
            'ordinal' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'governance' => ['nullable', 'string'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'reference',
                'description',
                'governance',
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
                ->where('requirement_source_id', $model->requirement_source_id)
                ->max('ordinal');

            $model->ordinal = $lastOrdinal !== null ? ((int) $lastOrdinal + 1) : 0;
        });

        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Requirements' : 'Requirement';
    }

    public function int_requirement_source(): BelongsTo
    {
        return $this->belongsTo(RequirementSource::class, 'requirement_source_id');
    }

    public function int_compliance_evaluation_requirement(): HasMany
    {
        return $this->hasMany(ComplianceEvaluationRequirement::class, 'requirement_id', 'id');
    }

    public function int_control_requirements(): HasMany
    {
        return $this->hasMany(ControlRequirement::class, 'requirement_id', 'id');
    }

    public function int_controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_requirements', 'requirement_id', 'control_id');
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
