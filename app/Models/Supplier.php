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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'processoragreementdescription', 'dataprocessor', 'external_supplier_id', 'tags'];

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
            'dataprocessor' => 'boolean',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'processoragreementdescription' => ['nullable', 'string'],
            'dataprocessor' => ['nullable', 'boolean'],
            'external_supplier_id' => ['nullable', 'string', 'max:255'],
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
                'processoragreementdescription',
                'external_supplier_id',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
        ];
    }

    public static function crudAppends(): array
    {
        return [
            'tags',
            'classified',
            'has_category_issues',
            'has_evaluation_issues',
            'process_activities_summary',
            'assets_summary',
            'supplier_categories_summary',
            'agreementscount',
        ];
    }

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        $query->withCount([
            'int_agreements as agreementscount',
        ]);

        if ($request->boolean('show_my_only') && $request->user()) {
            $query->where('responsible_user_id', $request->user()->id);
        }

        if ($request->boolean('hide_without_issues')) {
            $query->where(function (Builder $issueQuery): void {
                $issueQuery
                    ->whereNull('responsible_user_id')
                    ->orWhere(function (Builder $categoryQuery): void {
                        $categoryQuery->whereExists(function ($existsQuery): void {
                            $existsQuery
                                ->selectRaw('1')
                                ->from('supplier_categories')
                                ->whereNotExists(function ($subQuery): void {
                                    $subQuery
                                        ->selectRaw('1')
                                        ->from('supplier_supplier_category')
                                        ->whereColumn('supplier_supplier_category.supplier_id', 'suppliers.id')
                                        ->whereColumn('supplier_supplier_category.supplier_category_id', 'supplier_categories.id');
                                });
                        });
                    });
            });
        }

        $tagId = $request->integer('tag_id');
        if ($tagId > 0) {
            $query->whereHas('int_object_tags_as_object_tags', function (Builder $tagQuery) use ($tagId): void {
                $tagQuery->where('tag_id', $tagId);
            });
        }

        $supplierCategoryId = $request->integer('supplier_category_id');
        if ($supplierCategoryId > 0) {
            $query->whereHas('int_supplier_supplier_category', function (Builder $categoryQuery) use ($supplierCategoryId): void {
                $categoryQuery
                    ->where('supplier_category_id', $supplierCategoryId)
                    ->where('applicable', true);
            });
        }

        $responsibleUserId = $request->integer('responsible_user_id');
        if ($responsibleUserId > 0) {
            $query->where('responsible_user_id', $responsibleUserId);
        }
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

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Suppliers' : 'Supplier';
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

    public function getClassifiedAttribute(): bool
    {
        if (array_key_exists('responsible_user_id', $this->attributes)) {
            return (int) ($this->attributes['responsible_user_id'] ?? 0) > 0;
        }

        if ($this->exists) {
            $responsibleUserId = static::query()
                ->whereKey($this->getKey())
                ->value('responsible_user_id');

            return (int) ($responsibleUserId ?? 0) > 0;
        }

        return $this->int_responsible_user()->exists();
    }

    public function getHasCategoryIssuesAttribute(): bool
    {
        return collect($this->getSupplierCategoriesSummaryAttribute())
            ->contains(static fn (array $category): bool => $category['applicable'] === null);
    }

    public function getHasEvaluationIssuesAttribute(): bool
    {
        $applicableCategoryIds = $this->int_supplier_supplier_category()
            ->where('applicable', true)
            ->pluck('supplier_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($applicableCategoryIds === []) {
            return false;
        }

        $requirements = SupplierRequirement::query()
            ->whereIn('supplier_category_id', $applicableCategoryIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($requirements === []) {
            return false;
        }

        $evaluations = SupplierSupplierRequirement::query()
            ->where('supplier_id', $this->getKey())
            ->whereIn('supplier_requirement_id', $requirements)
            ->get(['supplier_requirement_id', 'satisfactory'])
            ->keyBy(static fn (SupplierSupplierRequirement $evaluation): int => (int) $evaluation->supplier_requirement_id);

        foreach ($requirements as $requirementId) {
            /** @var SupplierSupplierRequirement|null $evaluation */
            $evaluation = $evaluations->get($requirementId);
            if ($evaluation === null || $evaluation->satisfactory !== true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getProcessActivitiesSummaryAttribute(): array
    {
        return $this->int_process_activities()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->filter(static fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getAssetsSummaryAttribute(): array
    {
        return $this->int_assets()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->filter(static fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, applicable: bool|null}>
     */
    public function getSupplierCategoriesSummaryAttribute(): array
    {
        $statuses = $this->int_supplier_supplier_category()
            ->get(['supplier_category_id', 'applicable'])
            ->keyBy(static fn (SupplierSupplierCategory $category): int => (int) $category->supplier_category_id);

        return SupplierCategory::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (SupplierCategory $category) use ($statuses): array {
                /** @var SupplierSupplierCategory|null $status */
                $status = $statuses->get((int) $category->id);

                return [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'applicable' => $status?->applicable,
                ];
            })
            ->values()
            ->all();
    }

    public function getAgreementscountAttribute(): int
    {
        if (array_key_exists('agreementscount', $this->attributes)) {
            return (int) $this->attributes['agreementscount'];
        }

        return $this->int_agreements()->count();
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

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'supplier_id', 'id');
    }

    public function int_assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'supplier_id', 'id');
    }

    public function int_process_activity_supplier(): HasMany
    {
        return $this->hasMany(ProcessActivitySupplier::class, 'supplier_id', 'id');
    }

    public function int_supplier_documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class, 'supplier_id', 'id');
    }

    public function int_supplier_supplier_category(): HasMany
    {
        return $this->hasMany(SupplierSupplierCategory::class, 'supplier_id', 'id');
    }

    public function int_supplier_supplier_requirement(): HasMany
    {
        return $this->hasMany(SupplierSupplierRequirement::class, 'supplier_id', 'id');
    }

    public function int_process_activities(): BelongsToMany
    {
        return $this->belongsToMany(ProcessActivity::class, 'process_activity_supplier', 'supplier_id', 'process_activity_id');
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
