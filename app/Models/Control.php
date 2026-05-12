<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
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

class Control extends Model
{
    use HasFactory;
    use HasStatus;
    use HasTags;

    protected $table = 'controls';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'statusdescription', 'reviewed_at', 'tags'];

    protected $appends = ['tags'];

    /**
     * @var array<int, string>
     */
    private array $pendingTags = [];

    private bool $hasPendingTagsUpdate = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'statusdescription' => ['nullable', 'string'],
            'reviewed_at' => ['nullable', 'date'],
            'tags' => ['sometimes', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'statusdescription',
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
        if ($request->boolean('show_my_only') && $request->user()) {
            $query->where('responsible_user_id', $request->user()->id);
        }

        if ($request->boolean('hide_without_issues')) {
            $query->where(function (Builder $missingFieldsQuery): void {
                $missingFieldsQuery->whereNull('responsible_user_id');
            });
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
        return $plural ? 'Controls' : 'Control';
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_control_actions(): HasMany
    {
        return $this->hasMany(ControlAction::class, 'control_id', 'id');
    }

    public function int_control_requirements(): HasMany
    {
        return $this->hasMany(ControlRequirement::class, 'control_id', 'id');
    }

    public function int_control_project_type_risk_template(): HasMany
    {
        return $this->hasMany(ControlProjectTypeRiskTemplate::class, 'control_id', 'id');
    }

    public function int_control_risks(): HasMany
    {
        return $this->hasMany(ControlRisk::class, 'control_id', 'id');
    }

    public function int_requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'control_requirements', 'control_id', 'requirement_id');
    }

    public function int_project_type_risk_templates(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTypeRiskTemplate::class, 'control_project_type_risk_template', 'control_id', 'project_type_risk_template_id')
            ->withTimestamps();
    }

    public function int_risks(): BelongsToMany
    {
        return $this->belongsToMany(Risk::class, 'control_risks', 'control_id', 'risk_id');
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

    protected function resolveStatus(): array
   {
      if($this->not_applicable_at)
         return $this->defaultStatus('success', __("This control is set as not applicable"));

      if(!$this->responsible_user_id)
         return $this->defaultStatus('danger', __("A responsible user has not been assigned"));
      
      if(!$this->statusdescription)
         return $this->defaultStatus('warning', __("Status description is missing"));
      
      if($this->getPendingActionCountAttribute())
         return $this->defaultStatus('success', __("There are pending actions for this control"));
      
      return $this->defaultStatus('success', '');
      
   }

}

