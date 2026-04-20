<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Site extends Model
{
    use HasFactory;
    use HasTags;

    protected $table = 'sites';

    protected $fillable = [
        'name',
        'description',
        'responsible_user_id',
        'external_provider_group_id',
        'users',
        'departments',
        'assets',
        'tags',
    ];

    protected $appends = [
        'users',
        'departments',
        'assets',
        'userscount',
        'departmentscount',
        'assetscount',
        'classified',
        'can_delete',
        'tags',
    ];

    /** @var array<int, int>|null */
    private ?array $pendingUserIds = null;

    /** @var array<int, int>|null */
    private ?array $pendingDepartmentIds = null;

    /** @var array<int, int>|null */
    private ?array $pendingAssetIds = null;

    /** @var array<int, string> */
    private array $pendingTags = [];

    private bool $hasPendingTagsUpdate = false;

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
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'external_provider_group_id' => ['nullable', 'integer', 'min:0', 'exists:external_provider_groups,id'],
            'users' => ['nullable', 'array'],
            'departments' => ['nullable', 'array'],
            'assets' => ['nullable', 'array'],
            'tags' => ['sometimes', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
        ];
    }

    public static function crudAppends(): array
    {
        return [
            'users',
            'departments',
            'assets',
            'userscount',
            'departmentscount',
            'assetscount',
            'classified',
            'can_delete',
            'tags',
        ];
    }

    public static function applyCrudIndexFilters(mixed $query, Request $request): void
    {
        $query->withCount([
            'int_users as userscount',
            'int_departments as departmentscount',
            'int_assets as assetscount',
        ]);

        $tagId = $request->integer('tag_id');
        if ($tagId > 0) {
            $query->whereHas('int_object_tags_as_object_tags', function ($tagQuery) use ($tagId): void {
                $tagQuery->where('tag_id', $tagId);
            });
        }

        $responsibleUserId = $request->integer('responsible_user_id');
        if ($responsibleUserId > 0) {
            $query->where('responsible_user_id', $responsibleUserId);
        }

        if ($request->boolean('hide_without_issues')) {
            $query->whereNull('responsible_user_id');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });

        static::saved(function (self $model): void {
            $model->syncRelationsIfNeeded();
            $model->syncPendingTags();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Sites' : 'Site';
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
    public function getUsersAttribute(): array
    {
        return $this->int_users()
            ->pluck('users.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setUsersAttribute(mixed $value): void
    {
        $this->pendingUserIds = $this->normalizeIdArray($value);
    }

    /**
     * @return array<int, int>
     */
    public function getDepartmentsAttribute(): array
    {
        return $this->int_departments()
            ->pluck('departments.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setDepartmentsAttribute(mixed $value): void
    {
        $this->pendingDepartmentIds = $this->normalizeIdArray($value);
    }

    /**
     * @return array<int, int>
     */
    public function getAssetsAttribute(): array
    {
        return $this->int_assets()
            ->pluck('assets.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setAssetsAttribute(mixed $value): void
    {
        $this->pendingAssetIds = $this->normalizeIdArray($value);
    }

    public function getUserscountAttribute(): int
    {
        if (array_key_exists('userscount', $this->attributes)) {
            return (int) $this->attributes['userscount'];
        }

        return $this->int_users()->count();
    }

    public function getDepartmentscountAttribute(): int
    {
        if (array_key_exists('departmentscount', $this->attributes)) {
            return (int) $this->attributes['departmentscount'];
        }

        return $this->int_departments()->count();
    }

    public function getAssetscountAttribute(): int
    {
        if (array_key_exists('assetscount', $this->attributes)) {
            return (int) $this->attributes['assetscount'];
        }

        return $this->int_assets()->count();
    }

    public function getClassifiedAttribute(): bool
    {
        if (array_key_exists('responsible_user_id', $this->attributes)) {
            return (int) ($this->attributes['responsible_user_id'] ?? 0) > 0;
        }

        return $this->int_responsible_user()->exists();
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->userscount === 0
            && $this->departmentscount === 0
            && $this->assetscount === 0;
    }

    public function int_external_provider_group(): BelongsTo
    {
        return $this->belongsTo(ExternalProviderGroup::class, 'external_provider_group_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'site_id', 'id');
    }

    public function int_departments(): HasMany
    {
        return $this->hasMany(Department::class, 'site_id', 'id');
    }

    public function int_users(): HasMany
    {
        return $this->hasMany(User::class, 'site_id', 'id');
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

    /**
     * @return array<int, int>|null
     */
    private function normalizeIdArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value)) {
            return null;
        }

        return collect($value)
            ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function syncRelationsIfNeeded(): void
    {
        if ($this->pendingUserIds !== null) {
            User::query()
                ->where('site_id', $this->id)
                ->whereNotIn('id', $this->pendingUserIds)
                ->update(['site_id' => null]);

            if ($this->pendingUserIds !== []) {
                User::query()->whereIn('id', $this->pendingUserIds)->update(['site_id' => $this->id]);
            }

            $this->pendingUserIds = null;
        }

        if ($this->pendingDepartmentIds !== null) {
            Department::query()
                ->where('site_id', $this->id)
                ->whereNotIn('id', $this->pendingDepartmentIds)
                ->update(['site_id' => null]);

            if ($this->pendingDepartmentIds !== []) {
                Department::query()->whereIn('id', $this->pendingDepartmentIds)->update(['site_id' => $this->id]);
            }

            $this->pendingDepartmentIds = null;
        }

        if ($this->pendingAssetIds !== null) {
            Asset::query()
                ->where('site_id', $this->id)
                ->whereNotIn('id', $this->pendingAssetIds)
                ->update(['site_id' => null]);

            if ($this->pendingAssetIds !== []) {
                Asset::query()->whereIn('id', $this->pendingAssetIds)->update(['site_id' => $this->id]);
            }

            $this->pendingAssetIds = null;
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
}
