


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
class ProcessSustainabilityAspect extends Model
{
    use HasFactory;
    protected $table = 'process_sustainability_aspects';
    protected $fillable = ['name', 'description', 'impact_description', 'monitoring_description', 'governance_description', 'sustainability_aspect_id', 'process_id'];

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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'impact_description' => ['nullable', 'string'],
            'monitoring_description' => ['nullable', 'string'],
            'governance_description' => ['nullable', 'string'],
            'sustainability_aspect_id' => ['required', 'integer', 'min:0', 'exists:sustainability_aspects,id'],
            'process_id' => ['required', 'integer', 'min:0', 'exists:processes,id'],
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
        return $plural ? 'Process Sustainability Aspects' : 'Process Sustainability Aspect';
    }

    public function int_process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function int_sustainability_aspect(): BelongsTo
    {
        return $this->belongsTo(SustainabilityAspect::class, 'sustainability_aspect_id');
    }

    public function int_objective_process_sustainability_aspect(): HasMany
    {
        return $this->hasMany(ObjectiveProcessSustainabilityAspect::class, 'process_sustainability_aspect_id', 'id');
    }

    public function int_process_performance_metric_process_sustainability_aspect(): HasMany
    {
        return $this->hasMany(ProcessPerformanceMetricProcessSustainabilityAspect::class, 'process_sustainability_aspect_id', 'id');
    }

    public function int_process_sustainability_aspect_sustainability_metric(): HasMany
    {
        return $this->hasMany(ProcessSustainabilityAspectSustainabilityMetric::class, 'process_sustainability_aspect_id', 'id');
    }

    public function int_objectives(): BelongsToMany
    {
        return $this->belongsToMany(Objective::class, 'objective_process_sustainability_aspect', 'process_sustainability_aspect_id', 'objective_id')
            ->withTimestamps();
    }

    public function int_process_performance_metrics(): BelongsToMany
    {
        return $this->belongsToMany(ProcessPerformanceMetric::class, 'process_performance_metric_process_sustainability_aspect', 'process_sustainability_aspect_id', 'process_performance_metric_id')
            ->withTimestamps();
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
