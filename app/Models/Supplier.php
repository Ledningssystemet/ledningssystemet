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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

class Supplier extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
       if(config('ledningssystemet.disable_supplier', false))
           return [];

      if (null != $department)
         return [];

      if ((null != $user) && $user->cannot('update', Supplier::class))
         return [];

      $retval = [];
      $url = ((($user != null) && $user->can('index', get_called_class())) ||
         (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
         ? url()->query('/inventory/suppliers')
         : null;

      // Without assignment – no user filter
      $countWithoutAssignment = Supplier::whereNull('responsible_user_id')->count();
      if (!$personalOnly && $countWithoutAssignment)
         $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => Supplier::getPrettyName($countWithoutAssignment > 1).' '.__("without assignment"), 'url' => $url];

      $table   = (new self())->getTable();
      $scope   = Supplier::query()->when($user, fn (Builder $q) => $q->where('responsible_user_id', $user->id));
      $totalCategories = SupplierCategory::count();

      // Unclassified: dataprocessor set but missing property flags (mirrors isClassified() logic)
      $unclassified = (clone $scope)
         ->whereNotNull('dataprocessor')
         ->whereExists(function ($q) use ($table) {
            $q->selectRaw('1')
               ->from('properties')
               ->join('property_tabs', 'property_tabs.id', '=', 'properties.property_tab_id')
               ->leftJoin('object_properties as op', function ($join) use ($table) {
                  $join->on('op.property_id', '=', 'properties.id')
                     ->where('op.object_properties_type', self::class)
                     ->whereColumn('op.object_properties_id', $table.'.id');
               })
               ->where('property_tabs.context', self::class)
               ->whereNull('op.id');
         })
         ->count();

      // Uncategorized: fewer assessed categories than the total
      $uncategorized = (clone $scope)
         ->whereRaw(
            '(SELECT COUNT(*) FROM supplier_supplier_category WHERE supplier_id = '.$table.'.id) != ?',
            [$totalCategories]
         )
         ->count();

      // Fully categorized scope – base for unevaluated/notapproved (mirrors original else-branch)
      $categorizedScope = (clone $scope)->whereRaw(
         '(SELECT COUNT(*) FROM supplier_supplier_category WHERE supplier_id = '.$table.'.id) = ?',
         [$totalCategories]
      );

      // Unevaluated: categorized but has an applicable requirement with no evaluation row
      $unevaluated = (clone $categorizedScope)
         ->whereExists(function ($q) use ($table) {
            $q->selectRaw('1')
               ->from('supplier_requirements as sr')
               ->join('supplier_categories as sc', 'sc.id', '=', 'sr.supplier_category_id')
               ->join('supplier_supplier_category as ssc', function ($j) use ($table) {
                  $j->on('ssc.supplier_category_id', '=', 'sc.id')
                     ->whereColumn('ssc.supplier_id', $table.'.id')
                     ->where('ssc.applicable', true);
               })
               ->leftJoin('supplier_supplier_requirement as ssr', function ($j) use ($table) {
                  $j->on('ssr.supplier_requirement_id', '=', 'sr.id')
                     ->whereColumn('ssr.supplier_id', $table.'.id');
               })
               ->whereNull('ssr.id');
         })
         ->count();

      // Not approved: categorized and has an applicable requirement evaluated as unsatisfactory
      $notapproved = (clone $categorizedScope)
         ->whereExists(function ($q) use ($table) {
            $q->selectRaw('1')
               ->from('supplier_requirements as sr')
               ->join('supplier_categories as sc', 'sc.id', '=', 'sr.supplier_category_id')
               ->join('supplier_supplier_category as ssc', function ($j) use ($table) {
                  $j->on('ssc.supplier_category_id', '=', 'sc.id')
                     ->whereColumn('ssc.supplier_id', $table.'.id')
                     ->where('ssc.applicable', true);
               })
               ->join('supplier_supplier_requirement as ssr', function ($j) use ($table) {
                  $j->on('ssr.supplier_requirement_id', '=', 'sr.id')
                     ->whereColumn('ssr.supplier_id', $table.'.id');
               })
               ->where('ssr.satisfactory', false);
         })
         ->count();

      // Overdue: fetch all reassessment candidates in one query, evaluate interval in PHP
      $overdueRows = DB::table('suppliers as s')
         ->when($user, fn ($q) => $q->where('s.responsible_user_id', $user->id))
         ->join('supplier_supplier_category as ssc', function ($j) {
            $j->on('ssc.supplier_id', '=', 's.id')->where('ssc.applicable', true);
         })
         ->join('supplier_categories as sc', 'sc.id', '=', 'ssc.supplier_category_id')
         ->whereNotNull('sc.reassessment_interval')
         ->join('supplier_requirements as sr', function ($j) {
            $j->on('sr.supplier_category_id', '=', 'sc.id')->where('sr.reassessment', true);
         })
         ->join('supplier_supplier_requirement as ssr', function ($j) {
            $j->on('ssr.supplier_requirement_id', '=', 'sr.id')->on('ssr.supplier_id', '=', 's.id');
         })
         ->select('s.id as supplier_id', 'ssr.updated_at', 'sc.reassessment_interval')
         ->get();

      $overdueSupplierIds = [];
      foreach ($overdueRows as $row) {
         if (!isset($overdueSupplierIds[$row->supplier_id]) &&
            strtotime($row->reassessment_interval, strtotime($row->updated_at)) < time()) {
            $overdueSupplierIds[$row->supplier_id] = true;
         }
      }
      $numoverdue = count($overdueSupplierIds);

      if ($unclassified)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $unclassified, 'text' => Supplier::getPrettyName($unclassified > 1).' '.__("without classification"), 'url' => $url];

      if ($uncategorized)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $uncategorized, 'text' => Supplier::getPrettyName($uncategorized > 1).' '.__("without categorization"), 'url' => $url];

      if ($unevaluated)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $unevaluated, 'text' => Supplier::getPrettyName($unevaluated > 1).' '.__("without evaluation"), 'url' => $url];

      if ($notapproved)
         $retval[] = ['level' => 'warning', 'count' => $notapproved, 'text' => Supplier::getPrettyName($notapproved > 1).' '.__("with failed requirements"), 'url' => $url];

      if ($numoverdue)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $numoverdue, 'text' => Supplier::getPrettyName($numoverdue > 1).' '.__('needs re-evaluation'), 'url' => $url];

      return $retval;
   }

    use HasFactory;
    use HasStatus;

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

    public function int_supplier_category_assessments(): HasMany
    {
        return $this->int_supplier_supplier_category();
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

    protected function resolveStatus(): array
   {
      if (!$this->responsible_user_id)
         return $this->defaultStatus('danger', __("A responsible user has not been assigned"));

      $totalCategories = Cache::rememberForever('SupplierCategory.count', fn() => SupplierCategory::count());
      $isOwner = null !== auth()->user() && auth()->user()->id == $this->responsible_user_id;

      // Prefer precomputed selectSub value, then loaded relation, then query
      if (array_key_exists('supplier_category_count_db', $this->attributes)) {
         $assessmentCount = intval($this->attributes['supplier_category_count_db']);
      } elseif ($this->relationLoaded('int_supplier_category_assessments')) {
         $assessmentCount = $this->int_supplier_category_assessments->count();
      } else {
         $assessmentCount = $this->int_supplier_category_assessments()->count();
      }

      if ($assessmentCount != $totalCategories)
         return ['level' => $isOwner ? 'danger' : 'warning', 'text' => __("The supplier has not been categorized")];

      $hasUnevaluated = array_key_exists('has_unevaluated_requirements_db', $this->attributes)
         ? intval($this->attributes['has_unevaluated_requirements_db']) > 0
         : $this->int_supplier_requirements()->whereNull('supplier_supplier_requirement.id')->exists();

      if ($hasUnevaluated)
         return $this->defaultStatus($isOwner ? 'danger' : 'warning',__("The supplier has not been evaluated"));

      $hasFailed = array_key_exists('has_failed_requirements_db', $this->attributes)
         ? intval($this->attributes['has_failed_requirements_db']) > 0
         : $this->int_supplier_requirements()->where('satisfactory', false)->exists();

      if ($hasFailed)
         return $this->defaultStatus('warning', __("The supplier does not fulfil mandatory requirements"));

      // Overdue check â€“ must stay in PHP due to strtotime() interval format
      foreach ($this->int_supplier_requirements()->where('supplier_requirements.reassessment', true)->whereNotNull('supplier_categories.reassessment_interval')->select(['supplier_supplier_requirement.updated_at', 'supplier_categories.reassessment_interval'])->get() as $req) {
         if (strtotime($req->reassessment_interval, strtotime($req->updated_at)) < time())
            return $this->defaultStatus($isOwner ? 'danger' : 'warning',__("Supplier re-evaluation is overdue"));
      }

      if (!$this->classified)
         return $this->defaultStatus('warning', __("The supplier has not been classified"));

      return $this->defaultStatus('success', '');
   }

}
