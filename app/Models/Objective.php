<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
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

class Objective extends Model
{
    use HasFactory;
    use HasTags;

    protected $table = 'objectives';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'due', 'archived_at', 'action_plan', 'department_id', 'tags'];

    protected $appends = ['tags'];

    /**
     * @var array<int, string>
     */
    private array $pendingTags = [];

    private bool $hasPendingTagsUpdate = false;

    protected function casts(): array
    {
        return [
            'due' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'due' => ['nullable', 'date'],
            'archived_at' => ['nullable', 'date'],
            'action_plan' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['nullable', 'string', 'max:25'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'action_plan',
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
            $model->syncPendingTags();
        });
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

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        if (! $request->boolean('show_archived')) {
            $query->whereNull('archived_at');
        }

        if ($request->boolean('show_my_only') && $request->user()) {
            $query->where('responsible_user_id', $request->user()->id);
        }

        $tagId = $request->integer('tag_id');
        if ($tagId > 0) {
            $query->whereHas('int_object_tags_as_object_tags', function (Builder $tagQuery) use ($tagId): void {
                $tagQuery->where('tag_id', $tagId);
            });
        }

        $responsibleUserId = $request->integer('responsible_user_id');
        if ($responsibleUserId > 0) {
            $query->where('responsible_user_id', $responsibleUserId);
        }
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

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Objectives' : 'Objective';
    }

    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_control_action_mappings(): HasMany
    {
        return $this->hasMany(ControlActionMapping::class, 'objective_id', 'id');
    }

    public function int_objective_process_performance_metrics(): HasMany
    {
        return $this->hasMany(ObjectiveProcessPerformanceMetric::class, 'objective_id', 'id');
    }

    public function int_objective_process_sustainability_aspect(): HasMany
    {
        return $this->hasMany(ObjectiveProcessSustainabilityAspect::class, 'objective_id', 'id');
    }

    public function int_process_sustainability_aspects(): BelongsToMany
    {
        return $this->belongsToMany(ProcessSustainabilityAspect::class, 'objective_process_sustainability_aspect', 'objective_id', 'process_sustainability_aspect_id')
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
}
