<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

class ProcessPerformanceMetricReport extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      if(null != $department)
         return [];

      return $retval;
   }

    use HasFactory;

    protected $table = 'process_performance_metric_reports';

    protected $fillable = ['process_performance_metric_id', 'reported_by_id', 'value', 'reportedprecision', 'reporting_date_at', 'comment', 'reportvalue'];

    protected $appends = ['reportvalue', 'calculatedvalue', 'reported_by_name'];

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
            'reportvalue' => ['sometimes', 'nullable', 'numeric'],
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
            if ($model->reported_by_id === null && request()->user()) {
                $model->reported_by_id = request()->user()->id;
            }

            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        $query->with('int_reported_by:id,name');
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Process Performance Metric Reports' : 'Process Performance Metric Report';
    }

    public function getReportvalueAttribute(): ?float
    {
        if ($this->value === null) {
            return null;
        }

        $precision = max(0, (int) ($this->reportedprecision ?? 0));

        return ((int) $this->value) / (10 ** $precision);
    }

    public function setReportvalueAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['value'] = null;

            if (! array_key_exists('reportedprecision', $this->attributes)) {
                $this->attributes['reportedprecision'] = $this->resolveMetricPrecision();
            }

            return;
        }

        $precision = array_key_exists('reportedprecision', $this->attributes)
            ? max(0, (int) $this->attributes['reportedprecision'])
            : $this->resolveMetricPrecision();

        $numericValue = (float) $value;
        $scaled = (int) round($numericValue * (10 ** $precision));

        $this->attributes['reportedprecision'] = $precision;
        $this->attributes['value'] = $scaled;
    }

    public function getCalculatedvalueAttribute(): ?float
    {
        return $this->reportvalue;
    }

    public function getReportedByNameAttribute(): string
    {
        if ($this->relationLoaded('int_reported_by')) {
            return (string) ($this->int_reported_by?->name ?? '');
        }

        return (string) ($this->int_reported_by()->value('name') ?? '');
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

    private function resolveMetricPrecision(): int
    {
        if (! empty($this->process_performance_metric_id)) {
            $precision = ProcessPerformanceMetric::query()
                ->whereKey($this->process_performance_metric_id)
                ->value('precision');

            if ($precision !== null) {
                return max(0, (int) $precision);
            }
        }

        return 0;
    }
}
