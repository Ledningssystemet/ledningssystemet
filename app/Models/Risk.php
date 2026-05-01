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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Sorts\Sort;
use Throwable;

class Risk extends Model
{
    use HasFactory;
    use HasStatus;

    protected $table = 'risks';

    protected $fillable = ['name', 'context_type', 'department_id', 'context_id', 'scenariodescription', 'consequencedescription', 'riskowner_id', 'replacing_id', 'replacedby_id', 'assessed_at', 'replaced_at', 'created_by', 'probability_id', 'consequence_id', 'assessmentcomment', 'project_id', 'post_probability_id', 'post_consequence_id', 'tags', 'risk_controls'];

    protected $appends = ['tags', 'risk_controls', 'risk_level_id', 'translated_name', 'translated_scenariodescription', 'translated_consequencedescription'];

    /**
     * @var array<int, string>
     */
    private array $pendingTags = [];

    private bool $hasPendingTagsUpdate = false;

    /**
     * @var array<int, int>
     */
    private array $pendingControlIds = [];

    private bool $hasPendingControlUpdate = false;


    public function getTranslatedNameAttribute(): ?string
    {
        // If 'name' was not selected (e.g. $select only requested translated_name),
        // lazy-load it so the placeholder replacement has something to work with.
        // Generic CRUD may request only appended attributes via $select.
        if (! array_key_exists('name', $this->attributes) && $this->exists && $this->getKey()) {
            $fresh = static::query()->select(['id', 'name'])->find($this->getKey());
            if ($fresh) {
                $this->attributes['name'] = $fresh->attributes['name'] ?? null;
            }
        }

        return $this->replaceContextNamePlaceholder($this->attributes['name'] ?? null);
    }

    public function getTranslatedScenariodescriptionAttribute(): ?string
    {
        return $this->replaceContextNamePlaceholder($this->attributes['scenariodescription'] ?? null);
    }

    public function getTranslatedConsequencedescriptionAttribute(): ?string
    {
        return $this->replaceContextNamePlaceholder($this->attributes['consequencedescription'] ?? null);
    }

    private function replaceContextNamePlaceholder(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }



        $contextName = $this->resolveContextNameSafely();
        if ($contextName === null) {
            return $value;
        }

        return preg_replace('/\{name\}/i', $contextName, $value) ?? $value;
    }

    private function resolveContextNameSafely(): ?string
    {
        $contextType = $this->context_type;
        $contextId = $this->context_id;

        // Lazy-load context_type and context_id if missing but model exists in DB
        if (($contextType === null || $contextId === null) && $this->exists && $this->getKey()) {
            $fresh = static::query()->select(['context_type', 'context_id'])->find($this->getKey());
            if ($fresh) {
                $contextType = $fresh->context_type;
                $contextId = $fresh->context_id;
            }
        }

        if ($contextType === null || $contextId === null) {
            return null;
        }

        try {
            // Temporarily update attributes so morphTo can resolve, then clean up after
            $originalContextType = $this->getAttribute('context_type');
            $originalContextId = $this->getAttribute('context_id');
            $needsCleanup = false;

            if ($originalContextType === null && $contextType !== null) {
                $this->setAttribute('context_type', $contextType);
                $needsCleanup = true;
            }
            if ($originalContextId === null && $contextId !== null) {
                $this->setAttribute('context_id', $contextId);
                $needsCleanup = true;
            }

            $context = $this->int_context;

            // Clean up: remove any attributes we added just for morphTo resolution
            if ($needsCleanup) {
                if ($originalContextType === null && isset($this->attributes['context_type'])) {
                    unset($this->attributes['context_type']);
                }
                if ($originalContextId === null && isset($this->attributes['context_id'])) {
                    unset($this->attributes['context_id']);
                }
            }
        } catch (Throwable) {
            // Legacy datasets may contain removed/renamed morph classes.
            // Clean up any temporary attributes we added
            if (isset($originalContextType) && $originalContextType === null && isset($this->attributes['context_type'])) {
                unset($this->attributes['context_type']);
            }
            if (isset($originalContextId) && $originalContextId === null && isset($this->attributes['context_id'])) {
                unset($this->attributes['context_id']);
            }
            return null;
        }

        if (! is_object($context) || ! isset($context->name)) {
            return null;
        }

        $name = trim((string) $context->name);

        return $name === '' ? null : $name;
    }

    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
            'replaced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'context_type' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', 'integer', 'min:0', 'exists:departments,id'],
            'context_id' => ['nullable', 'integer', 'min:0'],
            'scenariodescription' => ['nullable', 'string'],
            'consequencedescription' => ['nullable', 'string'],
            'riskowner_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'replacing_id' => ['nullable', 'integer', 'min:0', 'exists:risks,id'],
            'replacedby_id' => ['nullable', 'integer', 'min:0', 'exists:risks,id'],
            'assessed_at' => ['nullable', 'date'],
            'replaced_at' => ['nullable', 'date'],
            'created_by' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'probability_id' => ['nullable', 'integer', 'min:0', 'exists:probability_levels,id'],
            'consequence_id' => ['nullable', 'integer', 'min:0', 'exists:consequence_levels,id'],
            'assessmentcomment' => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'min:0', 'exists:projects,id'],
            'post_probability_id' => ['nullable', 'integer', 'min:0', 'exists:probability_levels,id'],
            'post_consequence_id' => ['nullable', 'integer', 'min:0', 'exists:consequence_levels,id'],
            'tags' => ['sometimes', 'array'],
            'risk_controls' => ['sometimes', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'context_type',
                'scenariodescription',
                'consequencedescription',
                'assessmentcomment',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
        ];
    }

    public static function crudAppends(): array
    {
        return ['tags', 'risk_controls', 'risk_level_id', 'translated_name', 'translated_scenariodescription', 'translated_consequencedescription'];
    }

    public static function crudSorts(): array
    {
        return [
            AllowedSort::custom('risk_level_ordinal', new class implements Sort {
                public function __invoke(Builder $query, bool $descending, string $property): void
                {
                    $direction = $descending ? 'desc' : 'asc';

                    $query->orderByRaw(
                        "(select rl.ordinal
                            from risk_level_mappings as rlm
                            inner join risk_levels as rl on rl.id = rlm.risk_level_id
                            where rlm.probability_level_id = coalesce(risks.post_probability_id, risks.probability_id)
                              and rlm.consequence_level_id = coalesce(risks.post_consequence_id, risks.consequence_id)
                            limit 1) {$direction}"
                    );

                    // Stable tie-breaker keeps top-10 deterministic.
                    $query->orderBy('risks.id', $direction);
                }
            }),
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });

        static::saved(function (self $model): void {
            $model->syncPendingTags();
            $model->syncPendingControls();
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

        if ($request->has('department_id')) {
            $departmentId = $request->integer('department_id');
            if ($departmentId > 0) {
                $query->where('department_id', $departmentId);
            } elseif ($departmentId === 0 && $request->user()) {
                $departmentIds = $request->user()->int_departments()->pluck('departments.id')->all();
                if ($departmentIds !== []) {
                    $query->whereIn('department_id', $departmentIds);
                }
            }
        }

        $contextType = trim((string) $request->query('context_type', ''));
        if ($contextType !== '' && $contextType !== '0') {
            $query->where('context_type', $contextType);
        }

        $probabilityId = $request->integer('probability_id');
        if ($probabilityId > 0) {
            $query->where('probability_id', $probabilityId);
        }

        $consequenceId = $request->integer('consequence_id');
        if ($consequenceId > 0) {
            $query->where('consequence_id', $consequenceId);
        }

        $riskOwnerId = $request->integer('riskowner_id');
        if ($riskOwnerId > 0) {
            $query->where('riskowner_id', $riskOwnerId);
        }

        $riskLevelId = $request->integer('risk_level_id');
        if ($riskLevelId > 0) {
            $query->whereExists(function ($mappingQuery) use ($riskLevelId): void {
                $mappingQuery
                    ->selectRaw('1')
                    ->from('risk_level_mappings as rlm')
                    ->whereColumn('rlm.probability_level_id', 'risks.probability_id')
                    ->whereColumn('rlm.consequence_level_id', 'risks.consequence_id')
                    ->where('rlm.risk_level_id', $riskLevelId);
            });
        }

        if(1 != (int) request()->input('replaced_risks', 0))
            $query->whereNull('replacedby_id');
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Risks' : 'Risk';
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

    /**
     * @return array<int, int>
     */
    public function getRiskControlsAttribute(): array
    {
        return $this->int_controls()->pluck('controls.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param array<int, int|string>|int|string|null $value
     */
    public function setRiskControlsAttribute(array|int|string|null $value): void
    {
        $rawControlIds = is_array($value)
            ? $value
            : ($value === null || $value === '' ? [] : [$value]);

        $this->pendingControlIds = collect($rawControlIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->hasPendingControlUpdate = true;
    }

    public function getRiskLevelIdAttribute(): ?int
    {
        $probabilityId = $this->probability_id;
        $consequenceId = $this->consequence_id;

        // Generic CRUD may request only appended attributes via $select.
        if (($probabilityId === null || $consequenceId === null) && $this->exists) {
            $selected = static::query()->select(['probability_id', 'consequence_id'])->find($this->getKey());
            $probabilityId = $selected?->probability_id;
            $consequenceId = $selected?->consequence_id;
        }

        if ($probabilityId === null || $consequenceId === null) {
            return null;
        }

        $riskLevelId = RiskLevelMapping::query()
            ->where('probability_level_id', $probabilityId)
            ->where('consequence_level_id', $consequenceId)
            ->value('risk_level_id');

        return $riskLevelId === null ? null : (int) $riskLevelId;
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

    private function syncPendingControls(): void
    {
        if (! $this->hasPendingControlUpdate) {
            return;
        }

        $this->int_controls()->sync($this->pendingControlIds);

        $this->pendingControlIds = [];
        $this->hasPendingControlUpdate = false;
    }

    public function int_consequence(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'consequence_id');
    }

    public function int_created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function int_post_consequence(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'post_consequence_id');
    }

    public function int_post_probability(): BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'post_probability_id');
    }

    public function int_probability(): BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'probability_id');
    }

    public function int_replacedby(): BelongsTo
    {
        return $this->belongsTo(Risk::class, 'replacedby_id');
    }

    public function int_replacing(): BelongsTo
    {
        return $this->belongsTo(Risk::class, 'replacing_id');
    }

    public function int_project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function int_riskowner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'riskowner_id');
    }

    public function int_control_action_mappings(): HasMany
    {
        return $this->hasMany(ControlActionMapping::class, 'risk_id', 'id');
    }

    public function int_control_risks(): HasMany
    {
        return $this->hasMany(ControlRisk::class, 'risk_id', 'id');
    }

    public function int_risks_by_replacedby(): HasMany
    {
        return $this->hasMany(Risk::class, 'replacedby_id', 'id');
    }

    public function int_risks_by_replacing(): HasMany
    {
        return $this->hasMany(Risk::class, 'replacing_id', 'id');
    }

    public function int_controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_risks', 'risk_id', 'control_id');
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

    public function int_vector_embeddings_as_embeddable(): MorphMany
    {
        return $this->morphMany(VectorEmbedding::class, 'embeddable', 'embeddable_type', 'embeddable_id');
    }
}
