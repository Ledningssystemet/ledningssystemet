<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use App\Models\Concerns\HasTags;
use App\Services\Bpmn\BpmnNamePropagationService;
use App\Services\Classification\InheritedClassificationResolver;
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

class Asset extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if (null != $department)
         return [];

      if ((null != $user) && $user->cannot('update', Asset::class))
         return [];

      $retval = [];
      $url = ((($user != null) && $user->can('index', get_called_class())) ||
         (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
         ? url()->query('/inventory/assets')
         : null;

      $countWithoutAssignment = Asset::whereNull('responsible_user_id')->count();
      if (!$personalOnly && $countWithoutAssignment)
         $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => Asset::getPrettyName($countWithoutAssignment > 1) . ' ' . __("without assignment"), 'url' => $url];

      $table = (new self())->getTable();
      $missingConfidentialityIds = self::getAssetIdsMissingCalculatedClass('confidentiality');
      $missingIntegrityIds = self::getAssetIdsMissingCalculatedClass('integrity');
      $missingAvailabilityIds = self::getAssetIdsMissingCalculatedClass('availability');

      $unclassified = Asset::query()
         ->when($user, fn (Builder $q) => $q->where('responsible_user_id', $user->id))
         ->where(function (Builder $q) use ($table, $missingConfidentialityIds, $missingIntegrityIds, $missingAvailabilityIds) {
            $q->whereExists(function ($sub) use ($table) {
               $sub->selectRaw('1')
                  ->from('properties')
                  ->join('property_tabs', 'property_tabs.id', '=', 'properties.property_tab_id')
                  ->leftJoin('object_properties as op', function ($join) use ($table) {
                     $join->on('op.property_id', '=', 'properties.id')
                        ->where('op.object_properties_type', self::class)
                        ->whereColumn('op.object_properties_id', $table . '.id');
                  })
                  ->where('property_tabs.context', self::class)
                  ->whereNull('op.id');
            });

            if (!empty($missingConfidentialityIds)) {
               $q->orWhereIn($table . '.id', $missingConfidentialityIds);
            }
            if (!empty($missingIntegrityIds)) {
               $q->orWhereIn($table . '.id', $missingIntegrityIds);
            }
            if (!empty($missingAvailabilityIds)) {
               $q->orWhereIn($table . '.id', $missingAvailabilityIds);
            }
         })
         ->count();


      if ($unclassified)
         $retval[] = ['level' => 'warning', 'count' => $unclassified, 'text' => Asset::getPrettyName($unclassified > 1) . ' ' . __("without classification"), 'url' => $url];

      return $retval;
   }

    use HasFactory;
    use HasStatus;
    use HasTags;

    protected $table = 'assets';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'supplier_id', 'confidentiality_class_id', 'integrity_class_id', 'availability_class_id', 'mtd', 'rpo', 'site_id', 'tags'];

    protected $appends = ['tags', 'effective_confidentiality_class_id', 'effective_integrity_class_id', 'effective_availability_class_id'];

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
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'supplier_id' => ['nullable', 'integer', 'min:0', 'exists:suppliers,id'],
            'confidentiality_class_id' => ['nullable', 'integer', 'min:0', 'exists:confidentiality_classes,id'],
            'integrity_class_id' => ['nullable', 'integer', 'min:0', 'exists:integrity_classes,id'],
            'availability_class_id' => ['nullable', 'integer', 'min:0', 'exists:availability_classes,id'],
            'mtd' => ['nullable', 'integer', 'min:0'],
            'rpo' => ['nullable', 'integer', 'min:0'],
            'site_id' => ['nullable', 'integer', 'min:0', 'exists:sites,id'],
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

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });

        static::updated(function (self $model): void {
            if (! $model->wasChanged('name')) {
                return;
            }

            app(BpmnNamePropagationService::class)->syncAssetRename($model, (string) $model->getOriginal('name'));
        });

        static::saved(function (self $model): void {
            $model->syncPendingTags();
            InheritedClassificationResolver::bumpCacheVersion();
        });

        static::deleted(static function (): void {
            InheritedClassificationResolver::bumpCacheVersion();
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
                $missingFieldsQuery
                    ->whereNull('responsible_user_id')
                    ->orWhereNull('confidentiality_class_id')
                    ->orWhereNull('integrity_class_id')
                    ->orWhereNull('availability_class_id');
            });
        }

        $tagId = $request->integer('tag_id');
        if ($tagId > 0) {
            $query->whereHas('int_object_tags_as_object_tags', function (Builder $tagQuery) use ($tagId): void {
                $tagQuery->where('tag_id', $tagId);
            });
        }

        $processId = $request->integer('process_id');
        if ($processId > 0) {
            $query->whereHas('int_asset_information_type', function (Builder $aitQuery) use ($processId): void {
                $aitQuery->where('process_id', $processId);
            });
        }

        $siteId = $request->integer('site_id');
        if ($siteId > 0) {
            $query->where('site_id', $siteId);
        }

        $confidentialityClassId = $request->integer('confidentiality_class_id');
        if ($confidentialityClassId > 0) {
            $query->where('confidentiality_class_id', $confidentialityClassId);
        }

        $integrityClassId = $request->integer('integrity_class_id');
        if ($integrityClassId > 0) {
            $query->where('integrity_class_id', $integrityClassId);
        }

        $availabilityClassId = $request->integer('availability_class_id');
        if ($availabilityClassId > 0) {
            $query->where('availability_class_id', $availabilityClassId);
        }

        $responsibleUserId = $request->integer('responsible_user_id');
        if ($responsibleUserId > 0) {
            $query->where('responsible_user_id', $responsibleUserId);
        }
    }

    /**
     * @return array<int, int>
     */
    private static function getAssetIdsMissingCalculatedClass(string $dimension): array
    {
        $column = self::classificationColumn($dimension);
        $resolver = app(InheritedClassificationResolver::class);
        $missingAssetIds = [];

        self::query()
            ->select('id')
            ->whereNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($assets) use ($dimension, $resolver, &$missingAssetIds): void {
                foreach ($assets as $asset) {
                    if ($resolver->resolveAsset((int) $asset->id, $dimension) === null) {
                        $missingAssetIds[] = (int) $asset->id;
                    }
                }
            });

        return $missingAssetIds;
    }

    private static function classificationColumn(string $dimension): string
    {
        return match ($dimension) {
            InheritedClassificationResolver::CONFIDENTIALITY => 'confidentiality_class_id',
            InheritedClassificationResolver::INTEGRITY => 'integrity_class_id',
            InheritedClassificationResolver::AVAILABILITY => 'availability_class_id',
            default => throw new \InvalidArgumentException('Unsupported classification dimension: '.$dimension),
        };
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
        return $plural ? 'Assets' : 'Asset';
    }

    public function int_site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function int_availability_class(): BelongsTo
    {
        return $this->belongsTo(AvailabilityClass::class, 'availability_class_id');
    }

    public function int_confidentiality_class(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityClass::class, 'confidentiality_class_id');
    }

    public function int_integrity_class(): BelongsTo
    {
        return $this->belongsTo(IntegrityClass::class, 'integrity_class_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function int_asset_asset_dependancy_by_dependant_asset(): HasMany
    {
        return $this->hasMany(AssetAssetDependancy::class, 'dependant_asset_id', 'id');
    }

    public function int_asset_asset_dependancy_by_depending_asset(): HasMany
    {
        return $this->hasMany(AssetAssetDependancy::class, 'depending_asset_id', 'id');
    }

    public function int_asset_information_type(): HasMany
    {
        return $this->hasMany(AssetInformationType::class, 'asset_id', 'id');
    }

    public function int_information_types(): BelongsToMany
    {
        return $this->belongsToMany(InformationType::class, 'asset_information_type', 'asset_id', 'information_type_id');
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

    public function getEffectiveConfidentialityClassIdAttribute(): ?int
    {
        return app(InheritedClassificationResolver::class)
            ->resolveAsset($this, InheritedClassificationResolver::CONFIDENTIALITY);
    }

    public function getEffectiveIntegrityClassIdAttribute(): ?int
    {
        return app(InheritedClassificationResolver::class)
            ->resolveAsset($this, InheritedClassificationResolver::INTEGRITY);
    }

    public function getEffectiveAvailabilityClassIdAttribute(): ?int
    {
        return app(InheritedClassificationResolver::class)
            ->resolveAsset($this, InheritedClassificationResolver::AVAILABILITY);
    }

    public function getConfidentialityClassCalculatedIdAttribute(): ?int
    {
        return $this->effective_confidentiality_class_id;
    }

    public function getIntegrityClassCalculatedIdAttribute(): ?int
    {
        return $this->effective_integrity_class_id;
    }

    public function getAvailabilityClassCalculatedIdAttribute(): ?int
    {
        return $this->effective_availability_class_id;
    }

    public function getClassifiedAttribute(): bool
    {
        return $this->getConfidentialityClassCalculatedIdAttribute() !== null
            && $this->getIntegrityClassCalculatedIdAttribute() !== null
            && $this->getAvailabilityClassCalculatedIdAttribute() !== null;
    }

    protected function resolveStatus(): array
   {
      if (!$this->responsible_user_id)
         return $this->defaultStatus('danger', __("A responsible user has not been assigned"));

      if ((null === ($confidentiality_class_id = $this->getEffectiveConfidentialityClassIdAttribute())) ||
         (null === ($integrity_class_id = $this->getEffectiveIntegrityClassIdAttribute())) ||
         (null === ($availability_class_id = $this->getEffectiveAvailabilityClassIdAttribute())))
         return $this->defaultStatus('warning', __("The asset has not been classified regarding information security"));

      $effectiveConfidentialityOrdinal = ConfidentialityClass::query()->whereKey($confidentiality_class_id)->value('ordinal');
      if (($effectiveConfidentialityOrdinal !== null) &&
         ((array_key_exists('has_higher_confidentiality_infotype', $this->attributes) &&
               intval($this->attributes['has_higher_confidentiality_infotype']) > 0) ||
            (!array_key_exists('has_higher_confidentiality_infotype', $this->attributes) && $this->int_information_types()
               ->whereNotNull('information_types.confidentiality_class_id')
               ->leftJoin('confidentiality_classes', 'confidentiality_classes.id', '=', 'information_types.confidentiality_class_id')
               ->where('confidentiality_classes.ordinal', '>', $effectiveConfidentialityOrdinal)
               ->exists())))
         return $this->defaultStatus('success', __("There are associated information types that are classified with a higher level of confidentiality than this asset"));

      $effectiveIntegrityOrdinal = IntegrityClass::query()->whereKey($integrity_class_id)->value('ordinal');
      if (($effectiveIntegrityOrdinal !== null) &&
         ((array_key_exists('has_higher_integrity_infotype', $this->attributes) &&
               intval($this->attributes['has_higher_integrity_infotype']) > 0) ||
            (!array_key_exists('has_higher_integrity_infotype', $this->attributes) && $this->int_information_types()
               ->whereNotNull('information_types.integrity_class_id')
               ->leftJoin('integrity_classes', 'integrity_classes.id', '=', 'information_types.integrity_class_id')
               ->where('integrity_classes.ordinal', '>', $effectiveIntegrityOrdinal)
               ->exists())))
         return $this->defaultStatus('success', __("There are associated information types that are classified with a higher level of integrity than this asset"));

      $effectiveAvailabilityOrdinal = AvailabilityClass::query()->whereKey($availability_class_id)->value('ordinal');
      if (($effectiveAvailabilityOrdinal !== null) &&
         ((array_key_exists('has_higher_availability_infotype', $this->attributes) &&
               intval($this->attributes['has_higher_availability_infotype']) > 0) ||
            (!array_key_exists('has_higher_availability_infotype', $this->attributes) && $this->int_information_types()
               ->whereNotNull('information_types.availability_class_id')
               ->leftJoin('availability_classes', 'availability_classes.id', '=', 'information_types.availability_class_id')
               ->where('availability_classes.ordinal', '>', $effectiveAvailabilityOrdinal)
               ->exists())))
         return $this->defaultStatus('success', __("There are associated information types that are classified with a higher level of availability than this asset"));

      return $this->defaultStatus('success', '');

   }

}

