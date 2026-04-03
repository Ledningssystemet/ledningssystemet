


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
class ProcessPerformanceMetric extends Model
{
    use HasFactory;
    protected $table = 'process_performance_metrics';
    protected $fillable = ['name', 'description', 'responsible_user_id', 'quantitative', 'biggerisbetter', 'unit', 'increment', 'minvalue', 'maxvalue', 'precision', 'postprocessing', 'alarm_threshold'];

    protected function casts(): array
    {
        return [
            'quantitative' => 'boolean',
            'biggerisbetter' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'quantitative' => ['required', 'boolean'],
            'biggerisbetter' => ['required', 'boolean'],
            'unit' => ['nullable', 'string', 'max:30'],
            'increment' => ['nullable', 'string', 'max:255'],
            'minvalue' => ['nullable', 'integer'],
            'maxvalue' => ['nullable', 'integer'],
            'precision' => ['nullable', 'integer', 'min:0'],
            'postprocessing' => ['nullable', 'string'],
            'alarm_threshold' => ['nullable', 'integer'],
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
        return $plural ? 'Process Performance Metrics' : 'Process Performance Metric';
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_objective_process_performance_metrics(): HasMany
    {
        return $this->hasMany(ObjectiveProcessPerformanceMetric::class, 'process_performance_metric_id', 'id');
    }

    public function int_process_performance_metric_process_sustainability_aspect(): HasMany
    {
        return $this->hasMany(ProcessPerformanceMetricProcessSustainabilityAspect::class, 'process_performance_metric_id', 'id');
    }

    public function int_process_performance_metric_reports(): HasMany
    {
        return $this->hasMany(ProcessPerformanceMetricReport::class, 'process_performance_metric_id', 'id');
    }

    public function int_process_process_performance_metric(): HasMany
    {
        return $this->hasMany(ProcessProcessPerformanceMetric::class, 'process_performance_metric_id', 'id');
    }

    public function int_process_sustainability_aspects(): BelongsToMany
    {
        return $this->belongsToMany(ProcessSustainabilityAspect::class, 'process_performance_metric_process_sustainability_aspect', 'process_performance_metric_id', 'process_sustainability_aspect_id')
            ->withTimestamps();
    }

    public function int_processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'process_process_performance_metric', 'process_performance_metric_id', 'process_id')
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
