<?php

namespace App\Models;

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

class ProcessSustainabilityAspect extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      $table = (new self())->getTable();

      // "without assessment" == status icon "help" == at least one required metric
      // for the selected sustainability aspect has no selected level for this PSA row.
      $count = self::query()
         ->whereExists(function ($q) use ($table) {
            $q->selectRaw('1')
               ->from('sustainability_aspect_sustainability_metric as sasm')
               ->leftJoin('process_sustainability_aspect_sustainability_metric as psasm', function ($join) use ($table) {
                  $join->on('psasm.sustainability_metric_id', '=', 'sasm.sustainability_metric_id')
                     ->whereColumn('psasm.process_sustainability_aspect_id', $table . '.id');
               })
               ->whereColumn('sasm.sustainability_aspect_id', $table . '.sustainability_aspect_id')
               ->whereNull('psasm.sustainability_metric_id');
         })
         ->count();

      if (!$personalOnly && $count) {
         $retval[] = [
            'level' => 'danger',
            'count' => $count,
            'text' => ProcessSustainabilityAspect::getPrettyName($count > 1) . ' ' . __("without assessment"),
            'url' => ((($user != null) && $user->can('index', get_called_class())) ||
               (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
               ? url()->query('/inventory/sustainabilityaspects')
               : null,
         ];
      }

      return $retval;
   }

    use HasFactory;

    protected $table = 'process_sustainability_aspects';

    protected $fillable = [
        'name',
        'description',
        'impact_description',
        'monitoring_description',
        'governance_description',
        'sustainability_aspect_id',
        'process_id',
        'tags',
        'objectives',
        'process_performance_metrics',
        'sustainability_metrics',
    ];

    protected $appends = [
        'tags',
        'process_name',
        'sustainability_aspect_name',
        'metric_sum',
        'significant',
        'sustainability_metrics',
        'objectives',
        'process_performance_metrics',
    ];

    /** @var array<int, string> */
    private array $pendingTags = [];

    private bool $hasPendingTagsUpdate = false;

    /** @var array<int, int>|null */
    private ?array $pendingObjectiveIds = null;

    /** @var array<int, int>|null */
    private ?array $pendingProcessPerformanceMetricIds = null;

    /** @var array<int, int>|null */
    private ?array $pendingSustainabilityMetricLevelsByMetricId = null;

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
            'tags' => ['sometimes', 'array'],
            'objectives' => ['sometimes', 'array'],
            'process_performance_metrics' => ['sometimes', 'array'],
            'sustainability_metrics' => ['sometimes', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'impact_description',
                'monitoring_description',
                'governance_description',
            ],
            'relations' => [
                'int_process' => ['name'],
                'int_sustainability_aspect' => ['name'],
            ],
        ];
    }

    public static function crudAppends(): array
    {
        return [
            'tags',
            'process_name',
            'sustainability_aspect_name',
            'metric_sum',
            'significant',
            'sustainability_metrics',
            'objectives',
            'process_performance_metrics',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });

        static::saved(function (self $model): void {
            $model->syncPendingTags();
            $model->syncObjectivesIfNeeded();
            $model->syncProcessPerformanceMetricsIfNeeded();
            $model->syncSustainabilityMetricsIfNeeded();
        });
    }

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        $tagId = $request->integer('tag_id');
        if ($tagId > 0) {
            $query->whereHas('int_object_tags_as_object_tags', function (Builder $tagQuery) use ($tagId): void {
                $tagQuery->where('tag_id', $tagId);
            });
        }
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Process Sustainability Aspects' : 'Process Sustainability Aspect';
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

    public function getProcessNameAttribute(): string
    {
        if ($this->relationLoaded('int_process')) {
            return (string) ($this->int_process?->name ?? '');
        }

        return (string) ($this->int_process()->value('name') ?? '');
    }

    public function getSustainabilityAspectNameAttribute(): string
    {
        if ($this->relationLoaded('int_sustainability_aspect')) {
            return (string) ($this->int_sustainability_aspect?->name ?? '');
        }

        return (string) ($this->int_sustainability_aspect()->value('name') ?? '');
    }

    /**
     * @return array<int, int>
     */
    public function getObjectivesAttribute(): array
    {
        $objectives = $this->relationLoaded('int_objectives')
            ? $this->int_objectives
            : $this->int_objectives()->select('objectives.id')->get();

        return $objectives
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<int, int|string>|int|string|null $value
     */
    public function setObjectivesAttribute(array|int|string|null $value): void
    {
        if ($value === null || $value === '') {
            $this->pendingObjectiveIds = [];

            return;
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        $this->pendingObjectiveIds = collect($value)
            ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function getProcessPerformanceMetricsAttribute(): array
    {
        $metrics = $this->relationLoaded('int_process_performance_metrics')
            ? $this->int_process_performance_metrics
            : $this->int_process_performance_metrics()->select('process_performance_metrics.id')->get();

        return $metrics
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<int, int|string>|int|string|null $value
     */
    public function setProcessPerformanceMetricsAttribute(array|int|string|null $value): void
    {
        if ($value === null || $value === '') {
            $this->pendingProcessPerformanceMetricIds = [];

            return;
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        $this->pendingProcessPerformanceMetricIds = collect($value)
            ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSustainabilityMetricsAttribute(): array
    {
        $aspectId = (int) ($this->sustainability_aspect_id ?? 0);
        if ($aspectId <= 0) {
            return [];
        }

        $metrics = SustainabilityMetric::query()
            ->whereHas('int_sustainability_aspects', function (Builder $query) use ($aspectId): void {
                $query->where('sustainability_aspects.id', $aspectId);
            })
            ->with(['int_sustainability_metric_levels' => function ($query): void {
                $query->orderBy('multiplier')->orderBy('id');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $selectedRows = $this->int_process_sustainability_aspect_sustainability_metric()
            ->with('int_sustainability_metric_level:id,name,description,multiplier')
            ->get(['id', 'sustainability_metric_id', 'sustainability_metric_level_id']);

        $selectedByMetricId = $selectedRows
            ->keyBy(static fn (ProcessSustainabilityAspectSustainabilityMetric $row): int => (int) $row->sustainability_metric_id);

        return $metrics->map(function (SustainabilityMetric $metric) use ($selectedByMetricId): array {
            /** @var ProcessSustainabilityAspectSustainabilityMetric|null $selected */
            $selected = $selectedByMetricId->get((int) $metric->id);

            return [
                'id' => (int) $metric->id,
                'name' => (string) $metric->name,
                'description' => $metric->description,
                'level' => $selected?->int_sustainability_metric_level
                    ? [
                        'sustainability_metric_level_id' => (int) $selected->sustainability_metric_level_id,
                        'id' => (int) $selected->sustainability_metric_level_id,
                        'name' => (string) $selected->int_sustainability_metric_level->name,
                        'description' => $selected->int_sustainability_metric_level->description,
                        'multiplier' => (int) $selected->int_sustainability_metric_level->multiplier,
                    ]
                    : null,
                'levels' => $metric->int_sustainability_metric_levels
                    ->map(static fn (SustainabilityMetricLevel $level): array => [
                        'id' => (int) $level->id,
                        'name' => (string) $level->name,
                        'description' => $level->description,
                        'multiplier' => (int) $level->multiplier,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * @param array<int|string, int|string|null>|null $value
     */
    public function setSustainabilityMetricsAttribute(?array $value): void
    {
        if ($value === null) {
            $this->pendingSustainabilityMetricLevelsByMetricId = [];

            return;
        }

        $this->pendingSustainabilityMetricLevelsByMetricId = collect($value)
            ->mapWithKeys(function (mixed $levelId, mixed $metricId): array {
                return [(int) $metricId => (int) $levelId];
            })
            ->filter(static fn (int $levelId, int $metricId): bool => $metricId > 0 && $levelId > 0)
            ->all();
    }

    public function getMetricSumAttribute(): ?int
    {
        $metricRows = $this->int_process_sustainability_aspect_sustainability_metric()
            ->with('int_sustainability_metric_level:id,multiplier')
            ->get();

        if ($metricRows->isEmpty()) {
            return null;
        }

        return $metricRows->sum(static fn (ProcessSustainabilityAspectSustainabilityMetric $row): int => (int) ($row->int_sustainability_metric_level?->multiplier ?? 0));
    }

    public function getSignificantAttribute(): ?bool
    {
        $metricSum = $this->metric_sum;
        if ($metricSum === null) {
            return null;
        }

        $threshold = (int) ($this->int_sustainability_aspect?->threshold ?? $this->int_sustainability_aspect()->value('threshold') ?? 0);

        return $metricSum >= $threshold;
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

    private function syncObjectivesIfNeeded(): void
    {
        if ($this->pendingObjectiveIds === null) {
            return;
        }

        $this->int_objectives()->sync($this->pendingObjectiveIds);
        $this->pendingObjectiveIds = null;
    }

    private function syncProcessPerformanceMetricsIfNeeded(): void
    {
        if ($this->pendingProcessPerformanceMetricIds === null) {
            return;
        }

        $this->int_process_performance_metrics()->sync($this->pendingProcessPerformanceMetricIds);
        $this->pendingProcessPerformanceMetricIds = null;
    }

    private function syncSustainabilityMetricsIfNeeded(): void
    {
        if ($this->pendingSustainabilityMetricLevelsByMetricId === null) {
            return;
        }

        $allowedMetricIds = SustainabilityAspectSustainabilityMetric::query()
            ->where('sustainability_aspect_id', (int) $this->sustainability_aspect_id)
            ->pluck('sustainability_metric_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $allowedMetricIdLookup = array_flip($allowedMetricIds);

        $this->int_process_sustainability_aspect_sustainability_metric()->delete();

        foreach ($this->pendingSustainabilityMetricLevelsByMetricId as $metricId => $levelId) {
            if (! isset($allowedMetricIdLookup[$metricId])) {
                continue;
            }

            $this->int_process_sustainability_aspect_sustainability_metric()->create([
                'sustainability_metric_id' => $metricId,
                'sustainability_metric_level_id' => $levelId,
            ]);
        }

        $this->pendingSustainabilityMetricLevelsByMetricId = null;
    }

    protected function resolveStatus(): array
   {
      $significant = $this->getSignificantAttribute();
      if(null === $significant)
         return $this->defaultStatus('danger', __("Aspect has not been assessed"));
      
      
      if($significant)
         return $this->defaultStatus('success', __("This is a significant aspect"));
      
      return $this->defaultStatus('success', '');
   }

}

