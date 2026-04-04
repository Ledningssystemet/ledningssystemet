<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class ProcessPerformanceMetricReport extends Model
{
    use HasFactory;

    protected $table = 'process_performance_metric_reports';

    protected $fillable = ['process_performance_metric_id', 'reported_by_id', 'value', 'reportedprecision', 'reporting_date_at', 'comment'];

    protected function casts(): array
    {
        return [
            'reporting_date_at' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'process_performance_metric_id' => ['required', 'integer', 'min:0', 'exists:process_performance_metrics,id'],
            'reported_by_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'value' => ['nullable', 'integer'],
            'reportedprecision' => ['nullable', 'integer', 'min:0'],
            'reporting_date_at' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'comment',
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
        return $plural ? 'Process Performance Metric Reports' : 'Process Performance Metric Report';
    }

    public function int_process_performance_metric(): BelongsTo
    {
        return $this->belongsTo(ProcessPerformanceMetric::class, 'process_performance_metric_id');
    }

    public function int_reported_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
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
