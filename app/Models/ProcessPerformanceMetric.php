<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

class ProcessPerformanceMetric extends Model
{
    use HasFactory;
    use HasStatus;

    protected $table = 'process_performance_metrics';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'quantitative', 'biggerisbetter', 'unit', 'increment', 'minvalue', 'maxvalue', 'precision', 'postprocessing', 'alarm_threshold', 'metric_type', 'process_ids', 'tags'];

    protected $appends = ['metric_type', 'process_ids', 'tags', 'reportcount'];

    /**
     * @var array<int, int>|null
     */
    private ?array $pendingProcessIds = null;

    /**
     * @var array<int, string>
     */
    private array $pendingTags = [];

    private bool $hasPendingTagsUpdate = false;

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
            'quantitative' => ['sometimes', 'boolean'],
            'biggerisbetter' => ['sometimes', 'boolean'],
            'metric_type' => ['sometimes', 'integer', 'in:1,2,3'],
            'unit' => ['nullable', 'string', 'max:30'],
            'increment' => ['nullable', 'string', 'max:255'],
            'minvalue' => ['nullable', 'integer'],
            'maxvalue' => ['nullable', 'integer'],
            'precision' => ['nullable', 'integer', 'min:0'],
            'postprocessing' => ['nullable', 'string'],
            'alarm_threshold' => ['nullable', 'integer'],
            'process_ids' => ['sometimes', 'array'],
            'tags' => ['sometimes', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'unit',
                'increment',
                'postprocessing',
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

        static::saved(function (self $model): void {
            $model->syncProcessesIfNeeded();
            $model->syncPendingTags();
        });
    }

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        $query->withCount([
            'int_process_performance_metric_reports as reportcount',
        ]);

        if ($request->boolean('show_my_only') && $request->user()) {
            $query->where('responsible_user_id', $request->user()->id);
        }

        $responsibleUserId = $request->integer('responsible_user_id');
        if ($responsibleUserId > 0) {
            $query->where('responsible_user_id', $responsibleUserId);
        }

        $processId = $request->integer('process_id');
        if ($processId > 0) {
            $query->whereHas('int_processes', function (Builder $processQuery) use ($processId): void {
                $processQuery->where('processes.id', $processId);
            });
        }

        $tagId = $request->integer('tag_id');
        if ($tagId > 0) {
            $query->whereHas('int_object_tags_as_object_tags', function (Builder $tagQuery) use ($tagId): void {
                $tagQuery->where('tag_id', $tagId);
            });
        }
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Process Performance Metrics' : 'Process Performance Metric';
    }

    public function getMetricTypeAttribute(): int
    {
        if (! $this->quantitative) {
            return 3;
        }

        return $this->biggerisbetter ? 1 : 2;
    }

    public function setMetricTypeAttribute(mixed $value): void
    {
        $metricType = (int) $value;

        if ($metricType === 3) {
            $this->attributes['quantitative'] = false;
            $this->attributes['biggerisbetter'] = false;

            return;
        }

        $this->attributes['quantitative'] = true;
        $this->attributes['biggerisbetter'] = $metricType !== 2;
    }

    /**
     * @return array<int, int>
     */
    public function getProcessIdsAttribute(): array
    {
        $processes = $this->relationLoaded('int_processes')
            ? $this->int_processes
            : $this->int_processes()->select('processes.id')->get();

        return $processes
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setProcessIdsAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->pendingProcessIds = [];

            return;
        }

        if (! is_array($value)) {
            return;
        }

        $this->pendingProcessIds = collect($value)
            ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getTagsAttribute(): array
    {
        return $this->int_object_tags_as_object_tags()
            ->with('int_tag:id,name')
            ->get()
            ->map(static fn (ObjectTag $objectTag): string => (string) ($objectTag->int_tag?->name ?? ''))
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string>|string|null $value
     */
    public function setTagsAttribute(array|string|null $value): void
    {
        $rawTags = is_array($value)
            ? $value
            : ($value === null || trim((string) $value) === '' ? [] : [(string) $value]);

        $this->pendingTags = collect($rawTags)
            ->map(static fn (mixed $tag): string => trim((string) $tag))
            ->filter(static fn (string $tag): bool => $tag !== '')
            ->unique()
            ->values()
            ->all();

        $this->hasPendingTagsUpdate = true;
    }

    public function getReportcountAttribute(): int
    {
        if (array_key_exists('reportcount', $this->attributes)) {
            return (int) $this->attributes['reportcount'];
        }

        return $this->int_process_performance_metric_reports()->count();
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

    private function syncProcessesIfNeeded(): void
    {
        if ($this->pendingProcessIds === null) {
            return;
        }

        $this->int_processes()->sync($this->pendingProcessIds);
        $this->pendingProcessIds = null;
    }

    private function syncPendingTags(): void
    {
        if (! $this->hasPendingTagsUpdate) {
            return;
        }

        if ($this->pendingTags === []) {
            $this->int_object_tags_as_object_tags()->delete();
            $this->pendingTags = [];
            $this->hasPendingTagsUpdate = false;

            return;
        }

        $tagIds = collect($this->pendingTags)
            ->map(static fn (string $tagName): int => (int) Tag::query()->firstOrCreate(['name' => $tagName])->id)
            ->all();

        $this->int_object_tags_as_object_tags()->delete();

        foreach ($tagIds as $tagId) {
            $this->int_object_tags_as_object_tags()->create([
                'tag_id' => $tagId,
            ]);
        }

        $this->pendingTags = [];
        $this->hasPendingTagsUpdate = false;
    }

    protected function resolveStatus(): array
   {
      if(!$this->responsible_user_id)
         return $this->defaultStatus('danger', __("A responsible user has not been assigned"));

      $lastreport = $this->int_last_report();
      if((null == $lastreport) || ((null != $this->report_interval) && (strtotime($this->increment, strtotime($lastreport->reporting_date_at)) < time())))
         return $this->defaultStatus('danger', __("Reporting is required"));

      // Calculate alarm threshold
      if((null != $lastreport) && $this->alarm_threshold && $this->quantitative)
      {
         if($this->biggerisbetter && ($lastreport->calculatedvalue <= $this->alarm_threshold))
            return $this->defaultStatus('warning', __("Alarm threshold breached"));
         else if(!$this->biggerisbetter && ($lastreport->calculatedvalue >= $this->alarm_threshold))
            return $this->defaultStatus('warning', __("Alarm threshold breached"));

      }

      return $this->defaultStatus('success', '');
      
   }

}

