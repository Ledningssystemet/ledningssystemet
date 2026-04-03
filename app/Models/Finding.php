<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Validator;

class Finding extends Model
{
    use HasFactory;

    protected $table = 'findings';

    protected $fillable = ['uid', 'name', 'description', 'department_id', 'finished_at', 'nonconformity', 'consequence', 'rootcause', 'immediateaction', 'preventativeaction', 'compliance_evaluation_requirement_finding_id', 'context_type', 'context_id', 'created_by', 'estimated_cost', 'distribution_analysis'];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
            'nonconformity' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'uid' => ['nullable', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'department_id' => ['required', 'integer', 'min:0', 'exists:departments,id'],
            'finished_at' => ['nullable', 'date'],
            'nonconformity' => ['required', 'boolean'],
            'consequence' => ['nullable', 'string'],
            'rootcause' => ['nullable', 'string'],
            'immediateaction' => ['nullable', 'string'],
            'preventativeaction' => ['nullable', 'string'],
            'compliance_evaluation_requirement_finding_id' => ['nullable', 'integer', 'min:0', 'exists:compliance_evaluation_requirement_findings,id'],
            'context_type' => ['nullable', 'string', 'max:255'],
            'context_id' => ['nullable', 'integer', 'min:0'],
            'created_by' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'estimated_cost' => ['nullable', 'integer', 'min:0'],
            'distribution_analysis' => ['nullable', 'string'],
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
        return $plural ? 'Findings' : 'Finding';
    }

    public function int_compliance_evaluation_requirement_finding(): BelongsTo
    {
        return $this->belongsTo(ComplianceEvaluationRequirementFinding::class, 'compliance_evaluation_requirement_finding_id');
    }

    public function int_created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function int_control_action_mappings(): HasMany
    {
        return $this->hasMany(ControlActionMapping::class, 'finding_id', 'id');
    }

    public function int_context(): MorphTo
    {
        return $this->morphTo('context', 'context_type', 'context_id');
    }

    public function int_custom_property_object_as_object(): MorphMany
    {
        return $this->morphMany(CustomPropertyObject::class, 'object', 'object_type', 'object_id');
    }

    public function int_files_as_object(): MorphMany
    {
        return $this->morphMany(File::class, 'object', 'object_type', 'object_id');
    }

    public function int_ignored_risks_as_context(): MorphMany
    {
        return $this->morphMany(IgnoredRisk::class, 'context', 'context_type', 'context_id');
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

    public function int_risk_template_evaluation_attempts_as_context(): MorphMany
    {
        return $this->morphMany(RiskTemplateEvaluationAttempt::class, 'context', 'context_type', 'context_id');
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
