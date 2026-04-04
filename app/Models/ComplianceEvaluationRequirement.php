<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class ComplianceEvaluationRequirement extends Model
{
    use HasFactory;

    protected $table = 'compliance_evaluation_requirement';

    protected $fillable = ['compliance_evaluation_id', 'requirement_id', 'cers_id', 'name', 'reference', 'description', 'governance', 'note', 'evaluated', 'applicable'];

    protected function casts(): array
    {
        return [
            'evaluated' => 'boolean',
            'applicable' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'compliance_evaluation_id' => ['required', 'integer', 'min:0', 'exists:compliance_evaluations,id'],
            'requirement_id' => ['required', 'integer', 'min:0', 'exists:requirements,id'],
            'cers_id' => ['required', 'integer', 'min:0', 'exists:compliance_evaluation_requirement_source,id'],
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'governance' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'evaluated' => ['required', 'boolean'],
            'applicable' => ['required', 'boolean'],
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
                'note',
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
        return $plural ? 'Compliance Evaluation Requirements' : 'Compliance Evaluation Requirement';
    }

    public function int_compliance_evaluation(): BelongsTo
    {
        return $this->belongsTo(ComplianceEvaluation::class, 'compliance_evaluation_id');
    }

    public function int_requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    public function int_cers(): BelongsTo
    {
        return $this->belongsTo(ComplianceEvaluationRequirementSource::class, 'cers_id');
    }

    public function int_compliance_evaluation_requirement_findings(): HasMany
    {
        return $this->hasMany(ComplianceEvaluationRequirementFinding::class, 'compliance_evaluation_requirement_id', 'id');
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
