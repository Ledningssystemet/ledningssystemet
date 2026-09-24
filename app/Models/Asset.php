<?php

namespace App\Models;

use App\Models\Concerns\DefersRelationAttributeSync;
use App\Traits\HasCustomProperties;
use App\Traits\HasMessages;
use App\Traits\HasNotifications;
use App\Traits\HasTags;
use Graphp\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Asset extends Model
{
    use DefersRelationAttributeSync, HasCustomProperties, HasMessages, HasNotifications, HasTags;

    public static function getPrettyName($plural = false)
    {
        if ($plural) {
            return __('Assets');
        } else {
            return __('Asset');
        }
    }

    /* Retrieve status for the entire collection of objects */
    public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
    {
        if ($department != null) {
            return [];
        }

        if (($user != null) && $user->cannot('update', Asset::class)) {
            return [];
        }

        $retval = [];
        $url = ((($user != null) && $user->can('index', get_called_class())) ||
           (($user == null) && (auth()->user() != null) && auth()->user()->can('index', get_called_class())))
           ? url()->query('/inventory/assets')
           : null;

        $countWithoutAssignment = Asset::whereNull('responsible_user_id')->count();
        if (! $personalOnly && $countWithoutAssignment) {
            $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => Asset::getPrettyName($countWithoutAssignment > 1).' '.__('without assignment'), 'url' => $url];
        }

        return $retval;
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        // Validate before save
        static::saving(function ($model) {
            Validator::make($model->toArray(), $model->getValidationRules())->validate();

            if ($model->name != htmlspecialchars($model->name)) {
                abort(400, $model->name.' '.__('is not a valid name. It contains special characters which are not allowed.'));
            }

            if (($model->id > 0) && ($model->getOriginal('name') != $model->name)) {
                $processes = [];

                // Calculate affected bpmn charts
                $processes = $model->int_information_types()->withPivot(['process_id'])->pluck('process_id')->unique()->toArray();

                // Update references in affected processes
                foreach ($processes as $proc_id) {
                    $proc = Process::findOrFail($proc_id);
                    $proc->int_rename_processobject($model, $model->getOriginal('name'), $model->name);
                }
            }
        });

        // Check that deleting is allowed
        static::deleting(function ($model) {
            // Don't delete models which has associated information types
            if ($model->int_information_types()->count()) {
                abort(403, __('It is not allowed to delete an asset that has associated information types'));
            }
        });

    }

    /**
     * Appended attributes
     */
    protected $appends = [
        'access',
        'processes',
        'informationtypes',
        'confidentiality_class_calculated_id',
        'integrity_class_calculated_id',
        'availability_class_calculated_id',
        'riskcount',
        'findingcount',
        'classified',
        'tags',
        'messagecount',
        'status',
        'dependant_assets',
        'depending_assets',
    ];

    public function getAccessAttribute($user = null)
    {
        if ($user == null) {
            $user = auth()->user();
        }

        if ($user == null) {
            return null;
        }

        return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
    }

    public function getStatusAttribute()
    {
        if (! $this->responsible_user_id) {
            return ['icon' => 'warning', 'level' => 'danger', 'text' => __('A responsible user has not been assigned')];
        }

        if (($this->getConfidentialityClassCalculatedIdAttribute() === null) ||
           ($this->getIntegrityClassCalculatedIdAttribute() === null) ||
           ($this->getAvailabilityClassCalculatedIdAttribute() === null)) {
            return ['icon' => 'warning', 'level' => 'warning', 'text' => __('The asset has not been classified regarding information security')];
        }

        // Prefer precomputed flags from index query when available.
        if (($this->confidentiality_class_id != null) &&
           ((array_key_exists('has_higher_confidentiality_infotype', $this->attributes) &&
                 intval($this->attributes['has_higher_confidentiality_infotype']) > 0) ||
              (! array_key_exists('has_higher_confidentiality_infotype', $this->attributes) && $this->int_information_types()
                  ->whereNotNull('information_types.confidentiality_class_id')
                  ->leftJoin('confidentiality_classes', 'confidentiality_classes.id', '=', 'information_types.confidentiality_class_id')
                  ->where('confidentiality_classes.ordinal', '>', $this->int_confidentiality_class->ordinal)
                  ->exists()))) {
            return ['icon' => 'info', 'level' => 'info', 'text' => __('There are associated information types that are classified with a higher level of confidentiality than this asset')];
        }

        if (($this->integrity_class_id != null) &&
           ((array_key_exists('has_higher_integrity_infotype', $this->attributes) &&
                 intval($this->attributes['has_higher_integrity_infotype']) > 0) ||
              (! array_key_exists('has_higher_integrity_infotype', $this->attributes) && $this->int_information_types()
                  ->whereNotNull('information_types.integrity_class_id')
                  ->leftJoin('integrity_classes', 'integrity_classes.id', '=', 'information_types.integrity_class_id')
                  ->where('integrity_classes.ordinal', '>', $this->int_integrity_class->ordinal)
                  ->exists()))) {
            return ['icon' => 'info', 'level' => 'info', 'text' => __('There are associated information types that are classified with a higher level of integrity than this asset')];
        }

        if (($this->availability_class_id != null) &&
           ((array_key_exists('has_higher_availability_infotype', $this->attributes) &&
                 intval($this->attributes['has_higher_availability_infotype']) > 0) ||
              (! array_key_exists('has_higher_availability_infotype', $this->attributes) && $this->int_information_types()
                  ->whereNotNull('information_types.availability_class_id')
                  ->leftJoin('availability_classes', 'availability_classes.id', '=', 'information_types.availability_class_id')
                  ->where('availability_classes.ordinal', '>', $this->int_availability_class->ordinal)
                  ->exists()))) {
            return ['icon' => 'info', 'level' => 'info', 'text' => __('There are associated information types that are classified with a higher level of availability than this asset')];
        }

        return ['icon' => 'check', 'level' => 'info', 'text' => ''];

    }

    public function getTagsAttribute()
    {
        if ($this->relationLoaded('tags')) {
            return $this->getRelation('tags');
        }

        return $this->tags()->get();
    }

    public function getMessagecountAttribute()
    {
        if (array_key_exists('messages_count', $this->attributes)) {
            return intval($this->attributes['messages_count']);
        }

        return $this->messages()->count();
    }

    public function getInformationtypesAttribute()
    {
        if ($this->relationLoaded('int_information_types')) {
            return $this->getRelation('int_information_types')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Asset.getInformationtypesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_information_types as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function setInformationtypesAttribute($value)
    {
        $this->syncRelationAttribute('informationtypes', fn ($model) => $model->int_information_types()->sync(($value != null) ? $value : []));
    }

    public function getProcessesAttribute()
    {
        if ($this->relationLoaded('int_processes')) {
            return $this->getRelation('int_processes')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Asset.getProcessesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_processes as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function getConfidentialityClassCalculatedIdAttribute()
    {
        $classifications = self::getCalculatedClassifications('confidentiality');
        $id = $classifications[$this->id] ?? null;

        return ($id === null || intval($id) <= 0) ? null : intval($id);
    }

    public function getIntegrityClassCalculatedIdAttribute()
    {
        $classifications = self::getCalculatedClassifications('integrity');
        $id = $classifications[$this->id] ?? null;

        return ($id === null || intval($id) <= 0) ? null : intval($id);
    }

    public function getAvailabilityClassCalculatedIdAttribute()
    {
        $classifications = self::getCalculatedClassifications('availability');
        $id = $classifications[$this->id] ?? null;

        return ($id === null || intval($id) <= 0) ? null : intval($id);
    }

    public function getRiskcountAttribute()
    {
        if (array_key_exists('int_risks_count', $this->attributes)) {
            return intval($this->attributes['int_risks_count']);
        }

        return $this->int_risks()->count();
    }

    public function getFindingcountAttribute()
    {
        if (array_key_exists('int_findings_count', $this->attributes)) {
            return intval($this->attributes['int_findings_count']);
        }

        return $this->int_findings()->count();
    }

    public function getClassifiedAttribute()
    {
        return ($this->getConfidentialityClassCalculatedIdAttribute() !== null) &&
               ($this->getIntegrityClassCalculatedIdAttribute() !== null) &&
               ($this->getAvailabilityClassCalculatedIdAttribute() !== null);
    }

    public function getDependantAssetsAttribute()
    {
        if ($this->relationLoaded('int_dependant_assets')) {
            return $this->getRelation('int_dependant_assets')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Asset.getDependantAssetsAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_dependant_assets as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function getDependingAssetsAttribute()
    {
        if ($this->relationLoaded('int_depending_assets')) {
            return $this->getRelation('int_depending_assets')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Asset.getDependingAssetsAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_depending_assets as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    /**
     * The attributes that shall be visible during serialization.
     */
    protected $visible = [
        'access',
        'id',
        'name',
        'description',
        'responsible_user_id',
        'created_at',
        'updated_at',
        'supplier_id',
        'confidentiality_class_id',
        'integrity_class_id',
        'availability_class_id',
        'mtd',
        'rpo',
        'informationtypes',
        'confidentiality_class_calculated_id',
        'integrity_class_calculated_id',
        'availability_class_calculated_id',
        'riskcount',
        'findingcount',
        'classified',
        'tags',
        'messagecount',
        'status',
        'site_id',
        'processes',
        'dependant_assets',
        'depending_assets',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'responsible_user_id',
        'supplier_id',
        'confidentiality_class_id',
        'integrity_class_id',
        'availability_class_id',
        'mtd',
        'rpo',
        'site_id',
        'informationtypes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
    ];

    /**
     * Custom actions
     */
    public $actions = [
        'dependantsList',
        'dependantsUpdate',
        'dependantsDelete',
        'dependencyGraph',
    ];

    /**
     * Index-function used for fetching multiple items via API
     */
    public static function index(User $user)
    {
        $table = (new self)->getTable();
        $search = trim((string) request()->input('search', ''));
        $showMyOnly = (intval(request()->input('showmyonly', 0)) === 1);
        $siteId = intval(request()->input('site_id', 0));
        $tagId = intval(request()->input('tag_id', 0));
        $responsibleUserId = intval(request()->input('responsible_user_id', 0));
        $id = intval(request()->input('id', 0));
        $processId = intval(request()->input('process_id', 0));
        $hideChecked = (bool) request()->input('hidechecked', 0);
        $confidentialityClassId = intval(request()->input('confidentiality_class_id', 0));
        $integrityClassId = intval(request()->input('integrity_class_id', 0));
        $availabilityClassId = intval(request()->input('availability_class_id', 0));

        $hasCustomPropertyFilter = count(array_filter(
            array_keys(request()->all()),
            static fn ($key) => (strpos($key, 'customproperty_') === 0)
        )) > 0;

        $query = self::query()
            ->select($table.'.*')
            ->selectSub(function ($sub) use ($table) {
                $sub->from('asset_information_type as ait')
                    ->join('information_types as it', 'it.id', '=', 'ait.information_type_id')
                    ->join('confidentiality_classes as cc', 'cc.id', '=', 'it.confidentiality_class_id')
                    ->join('confidentiality_classes as ac', 'ac.id', '=', $table.'.confidentiality_class_id')
                    ->whereColumn('ait.asset_id', $table.'.id')
                    ->whereNotNull('it.confidentiality_class_id')
                    ->whereColumn('cc.ordinal', '>', 'ac.ordinal')
                    ->selectRaw('COUNT(*)');
            }, 'has_higher_confidentiality_infotype')
            ->selectSub(function ($sub) use ($table) {
                $sub->from('asset_information_type as ait')
                    ->join('information_types as it', 'it.id', '=', 'ait.information_type_id')
                    ->join('integrity_classes as ic', 'ic.id', '=', 'it.integrity_class_id')
                    ->join('integrity_classes as ai', 'ai.id', '=', $table.'.integrity_class_id')
                    ->whereColumn('ait.asset_id', $table.'.id')
                    ->whereNotNull('it.integrity_class_id')
                    ->whereColumn('ic.ordinal', '>', 'ai.ordinal')
                    ->selectRaw('COUNT(*)');
            }, 'has_higher_integrity_infotype')
            ->selectSub(function ($sub) use ($table) {
                $sub->from('asset_information_type as ait')
                    ->join('information_types as it', 'it.id', '=', 'ait.information_type_id')
                    ->join('availability_classes as ac', 'ac.id', '=', 'it.availability_class_id')
                    ->join('availability_classes as aa', 'aa.id', '=', $table.'.availability_class_id')
                    ->whereColumn('ait.asset_id', $table.'.id')
                    ->whereNotNull('it.availability_class_id')
                    ->whereColumn('ac.ordinal', '>', 'aa.ordinal')
                    ->selectRaw('COUNT(*)');
            }, 'has_higher_availability_infotype')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'LIKE', '%'.$search.'%')
                        ->orWhere('description', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('tags', function (Builder $tagQuery) use ($search) {
                            $tagQuery->where('name', 'LIKE', '%'.$search.'%');
                        });
                });
            })
            ->when($showMyOnly, function (Builder $query) use ($user) {
                $query->where('responsible_user_id', $user->id);
            })
            ->when($siteId > 0, function (Builder $query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->when($tagId > 0, function (Builder $query) use ($tagId) {
                $query->whereHas('tags', function (Builder $q) use ($tagId) {
                    $q->where('tags.id', $tagId);
                });
            })
            ->when($responsibleUserId > 0, function (Builder $query) use ($responsibleUserId) {
                $query->where('responsible_user_id', $responsibleUserId);
            })
            ->when($id > 0, function (Builder $query) use ($id, $table) {
                $query->where($table.'.id', $id);
            })
            ->when($processId > 0, function (Builder $query) use ($processId) {
                $query->whereHas('int_processes', function (Builder $q) use ($processId) {
                    $q->where('processes.id', $processId);
                });
            })
            ->when($confidentialityClassId > 0, function (Builder $query) use ($table, $confidentialityClassId) {
                $ids = self::getAssetIdsByCalculatedClass('confidentiality', $confidentialityClassId);
                if (empty($ids)) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $query->whereIn($table.'.id', $ids);
            })
            ->when($integrityClassId > 0, function (Builder $query) use ($table, $integrityClassId) {
                $ids = self::getAssetIdsByCalculatedClass('integrity', $integrityClassId);
                if (empty($ids)) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $query->whereIn($table.'.id', $ids);
            })
            ->when($availabilityClassId > 0, function (Builder $query) use ($table, $availabilityClassId) {
                $ids = self::getAssetIdsByCalculatedClass('availability', $availabilityClassId);
                if (empty($ids)) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $query->whereIn($table.'.id', $ids);
            })
            ->when($hideChecked && (new self)->status, function (Builder $query) use ($table) {
                $missingConfidentialityIds = self::getAssetIdsMissingCalculatedClass('confidentiality');
                $missingIntegrityIds = self::getAssetIdsMissingCalculatedClass('integrity');
                $missingAvailabilityIds = self::getAssetIdsMissingCalculatedClass('availability');

                $query->where(function (Builder $q) use ($table, $missingConfidentialityIds, $missingIntegrityIds, $missingAvailabilityIds) {
                    $q->whereNull('responsible_user_id');

                    if (! empty($missingConfidentialityIds)) {
                        $q->orWhereIn($table.'.id', $missingConfidentialityIds);
                    }
                    if (! empty($missingIntegrityIds)) {
                        $q->orWhereIn($table.'.id', $missingIntegrityIds);
                    }
                    if (! empty($missingAvailabilityIds)) {
                        $q->orWhereIn($table.'.id', $missingAvailabilityIds);
                    }
                });
            })
            ->with([
                'tags',
                'int_information_types:id,name',
                'int_processes:id,name',
                'int_dependant_assets:id,name',
                'int_depending_assets:id,name',
            ])
            ->withCount([
                'messages',
                'int_risks',
                'int_findings',
            ])
            ->orderBy('name');

        if ($hasCustomPropertyFilter) {
            self::getIndexQuery($query);
        }

        return $query->paginate();
    }

    /**
     * Validation rules
     *
     * @var array<int, string>
     */
    public function getValidationRules()
    {
        return [
            'name' => [
                'required',
                'max:255',
                Rule::unique('assets')->ignore($this->id),
            ],
            'mtd' => 'nullable|numeric|min:0',
            'rpo' => 'nullable|numeric|min:0',
            'responsible_user_id' => 'nullable|exists:App\Models\User,id',
            'supplier_id' => 'nullable|exists:App\Models\Supplier,id',
            'confidentiality_class_id' => 'nullable|exists:App\Models\ConfidentialityClass,id',
            'integrity_class_id' => 'nullable|exists:App\Models\IntegrityClass,id',
            'availability_class_id' => 'nullable|exists:App\Models\AvailabilityClass,id',
            'site_id' => 'nullable|exists:App\Models\Site,id',
        ];
    }

    /**
     * Get the Information Types
     */
    public function int_information_types(): BelongsToMany
    {
        return $this->belongsToMany(InformationType::class)->distinct();
    }

    /**
     * Get the Processes
     */
    public function int_processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'asset_information_type')->distinct();
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function int_confidentiality_class(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityClass::class, 'confidentiality_class_id');
    }

    public function int_integrity_class(): BelongsTo
    {
        return $this->belongsTo(IntegrityClass::class, 'integrity_class_id');
    }

    public function int_availability_class(): BelongsTo
    {
        return $this->belongsTo(AvailabilityClass::class, 'availability_class_id');
    }

    public function int_site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Get assets that depend on this asset
     */
    public function int_depending_assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_asset_dependancy', 'dependant_asset_id', 'depending_asset_id')->withPivot(['inherit_confidentiality', 'inherit_integrity', 'inherit_availability', 'created_at', 'updated_at', 'description'])->distinct();
    }

    /**
     * Get assets that this asset depend on
     */
    public function int_dependant_assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_asset_dependancy', 'depending_asset_id', 'dependant_asset_id')->withPivot(['inherit_confidentiality', 'inherit_integrity', 'inherit_availability', 'created_at', 'updated_at', 'description'])->distinct();
    }

    private static function getCalculatedClassifications(string $aspect): array
    {
        $config = [
            'confidentiality' => [
                'cache_key' => 'Asset.confidentialityclassifications',
                'asset_column' => 'confidentiality_class_id',
                'infotype_column' => 'confidentiality_class_id',
                'inherit_column' => 'inherit_confidentiality',
                'class_model' => ConfidentialityClass::class,
            ],
            'integrity' => [
                'cache_key' => 'Asset.integrityclassifications',
                'asset_column' => 'integrity_class_id',
                'infotype_column' => 'integrity_class_id',
                'inherit_column' => 'inherit_integrity',
                'class_model' => IntegrityClass::class,
            ],
            'availability' => [
                'cache_key' => 'Asset.availabilityclassifications',
                'asset_column' => 'availability_class_id',
                'infotype_column' => 'availability_class_id',
                'inherit_column' => 'inherit_availability',
                'class_model' => AvailabilityClass::class,
            ],
        ];

        if (! array_key_exists($aspect, $config)) {
            return [];
        }

        $cfg = $config[$aspect];
        $assetColumn = $cfg['asset_column'];
        $infotypeColumn = $cfg['infotype_column'];
        $inheritColumn = $cfg['inherit_column'];
        $classModel = $cfg['class_model'];

        return Cache::rememberForever($cfg['cache_key'], function () use ($assetColumn, $infotypeColumn, $inheritColumn, $classModel) {
            $classes = [];
            foreach ($classModel::select(['id', 'ordinal'])->get() as $class) {
                $classes[$class->id] = $class->ordinal;
            }

            $classifications = [];
            $explicitset = [];

            foreach (self::select(['id', $assetColumn])->get() as $asset) {
                $classifications[$asset->id] = $asset->{$assetColumn};
                if ($asset->{$assetColumn} !== null) {
                    $explicitset[$asset->id] = true;
                }
            }

            foreach (
                DB::table('asset_information_type')
                    ->leftJoin('assets', 'assets.id', '=', 'asset_information_type.asset_id')
                    ->leftJoin('information_types', 'information_types.id', '=', 'asset_information_type.information_type_id')
                    ->whereNotNull('information_types.'.$infotypeColumn)
                    ->whereNull('assets.'.$assetColumn)
                    ->select([
                        'asset_information_type.asset_id',
                        DB::raw('information_types.'.$infotypeColumn.' as class_id'),
                    ])
                    ->get() as $row
            ) {
                $current = $classifications[$row->asset_id] ?? null;
                if (
                    ! array_key_exists($row->asset_id, $classifications) ||
                    $current === null ||
                    ((isset($classes[$row->class_id]) && isset($classes[$current])) && $classes[$row->class_id] > $classes[$current])
                ) {
                    $classifications[$row->asset_id] = $row->class_id;
                }
            }

            $dependencies = DB::table('asset_asset_dependancy')
                ->where($inheritColumn, true)
                ->select(['dependant_asset_id', 'depending_asset_id'])
                ->distinct()
                ->get()
                ->toArray();

            $updated = true;
            while ($updated) {
                $updated = false;

                foreach ($dependencies as $dependency) {
                    if (
                        ! array_key_exists($dependency->dependant_asset_id, $classifications) ||
                        ! array_key_exists($dependency->depending_asset_id, $classifications) ||
                        isset($explicitset[$dependency->depending_asset_id])
                    ) {
                        continue;
                    }

                    $dependantClass = $classifications[$dependency->dependant_asset_id];
                    $dependingClass = $classifications[$dependency->depending_asset_id];

                    if ($dependantClass === null) {
                        continue;
                    }

                    if (
                        $dependingClass === null ||
                        ((isset($classes[$dependantClass]) && isset($classes[$dependingClass])) && $classes[$dependantClass] > $classes[$dependingClass])
                    ) {
                        $classifications[$dependency->depending_asset_id] = $dependantClass;
                        $updated = true;
                    }
                }
            }

            return $classifications;
        });
    }

    private static function getAssetIdsByCalculatedClass(string $aspect, int $classId): array
    {
        return array_keys(array_filter(
            self::getCalculatedClassifications($aspect),
            static fn ($value) => ($value !== null) && (intval($value) === $classId)
        ));
    }

    private static function getAssetIdsMissingCalculatedClass(string $aspect): array
    {
        return array_keys(array_filter(
            self::getCalculatedClassifications($aspect),
            static fn ($value) => ($value === null) || (intval($value) <= 0)
        ));
    }

    /**
     * Get calculated confidentiality class
     */
    public function int_confidentialityclass()
    {
        $classifications = self::getCalculatedClassifications('confidentiality');
        $id = $classifications[$this->id] ?? null;
        if ($id === null || intval($id) <= 0) {
            return null;
        }

        return ConfidentialityClass::find($id);
    }

    /**
     * Get calculated integrity class
     */
    public function int_integrityclass()
    {
        $classifications = self::getCalculatedClassifications('integrity');
        $id = $classifications[$this->id] ?? null;
        if ($id === null || intval($id) <= 0) {
            return null;
        }

        return IntegrityClass::find($id);
    }

    /**
     * Get calculated availability class
     */
    public function int_availabilityclass()
    {
        $classifications = self::getCalculatedClassifications('availability');
        $id = $classifications[$this->id] ?? null;
        if ($id === null || intval($id) <= 0) {
            return null;
        }

        return AvailabilityClass::find($id);
    }

    public function isClassified()
    {
        return ($this->getConfidentialityClassCalculatedIdAttribute() !== null) &&
               ($this->getIntegrityClassCalculatedIdAttribute() !== null) &&
               ($this->getAvailabilityClassCalculatedIdAttribute() !== null);
    }

    /**
     * Get risks associated with this object
     */
    public function int_risks(): MorphMany
    {
        return $this->morphMany(Risk::class, 'context');
    }

    /**
     * Get findings associated with this process
     */
    public function int_findings(): MorphMany
    {
        return $this->morphMany(Finding::class, 'context');
    }

    /**
     * List dependants and their relation
     */
    public function dependantsList()
    {
        // Validate authorization
        if (auth()->user()->cannot('view', $this)) {
            abort(403);
        }

        $retval = [];
        foreach ($this->int_depending_assets()->orderBy('name')->withPivot(['id', 'inherit_confidentiality', 'inherit_integrity', 'inherit_availability', 'description'])->get() as $obj) {
            $retval[] = $obj->pivot->toArray();
        }

        return $retval;
    }

    /**
     * Create/update dependants and their relation
     */
    public function dependantsUpdate()
    {
        // Validate authorization
        if (auth()->user()->cannot('update', $this)) {
            abort(403);
        }

        $asset = Asset::find(request()->input('depending_asset_id'));
        if ($asset == null) {
            abort(404, __('Depending asset not found'));
        }

        $data = [
            'inherit_confidentiality' => request()->input('inherit_confidentiality', false),
            'inherit_integrity' => request()->input('inherit_integrity', false),
            'inherit_availability' => request()->input('inherit_availability', false),
            'description' => request()->input('description', ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (request()->has('id')) {
            // Update existing relation
            $this->int_depending_assets()->updateExistingPivot($asset->id, $data);
        } else {
            // Create new relation
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->int_depending_assets()->attach($asset->id, $data);
        }
    }

    /**
     * Delete relation
     */
    public function dependantsDelete()
    {
        // Validate authorization
        if (auth()->user()->cannot('update', $this)) {
            abort(403);
        }

        DB::table('asset_asset_dependancy')->where('id', request()->input('id', -1))->delete();
    }

    /**
     * Get dependency graph
     */
    public function dependencyGraph($depth = 0, $maxdepth = 5, $graph = null, $direction = 'both', $aspect = 'default', $highlightInfoClass = null)
    {
        $iconfontsize = 10;
        $edgefontsize = 10;
        $font = 'Albert Sans';

        // Validate authorization
        if (auth()->user()->cannot('view', $this)) {
            abort(403);
        }

        // Create graph if not provided
        if ($graph == null) {
            $graph = new Graph;
        }

        // Set aspect if provided
        if (($aspect == 'default') && request()->has('aspect')) {
            $aspect = request()->input('aspect', 'default');
        }

        if ($aspect != 'default') {
            $direction = 'up';
        }

        if (($depth == 0) && ($aspect == 'confidentiality')) {
            $highlightInfoClass = $this->int_confidentialityclass();
        } elseif (($depth == 0) && ($aspect == 'integrity')) {
            $highlightInfoClass = $this->int_integrityclass();
        } elseif (($depth == 0) && ($aspect == 'availability')) {
            $highlightInfoClass = $this->int_availabilityclass();
        }

        // Create this asset
        $currentAsset = $graph->createVertex();
        $currentAsset->setAttribute('id', 'asset_'.$this->id);
        $currentAsset->setAttribute('graphviz.label', $this->name);
        $currentAsset->setAttribute('graphviz.fontsize', $iconfontsize);
        $currentAsset->setAttribute('graphviz.shape', 'cylinder');
        $currentAsset->setAttribute('graphviz.fontname', $font);

        // Highlight root asset
        if ($depth == 0) {
            $currentAsset->setAttribute('graphviz.color', '#288c98');
        }

        // Create any information types
        if (($direction == 'both') || ($direction == 'up')) {
            foreach ($this->int_information_types()->get() as $informationType) {
                // Create information type vertex
                $infoTypeVertex = $graph->createVertex();
                $infoTypeVertex->setAttribute('id', 'infotype_'.$informationType->id);
                $infoTypeVertex->setAttribute('graphviz.label', $informationType->name);
                $infoTypeVertex->setAttribute('graphviz.fontsize', $iconfontsize);
                $infoTypeVertex->setAttribute('graphviz.shape', 'note');
                $infoTypeVertex->setAttribute('graphviz.fontname', $font);

                // Check if this information type should be highlighted
                $highlight = false;
                if (is_a($highlightInfoClass, ConfidentialityClass::class) && ($informationType->confidentiality_class_id == $highlightInfoClass->id)) {
                    $highlight = true;
                } elseif (is_a($highlightInfoClass, IntegrityClass::class) && ($informationType->integrity_class_id == $highlightInfoClass->id)) {
                    $highlight = true;
                } elseif (is_a($highlightInfoClass, AvailabilityClass::class) && ($informationType->availability_class_id == $highlightInfoClass->id)) {
                    $highlight = true;
                }

                if ($highlight) {
                    $infoTypeVertex->setAttribute('graphviz.color', '#f58726');
                }

                // Create edge
                $edge = $graph->createEdgeUndirected($infoTypeVertex, $currentAsset);
                $edge->setAttribute('graphviz.fontsize', $edgefontsize);
                $edge->setAttribute('graphviz.fontname', $font);
            }
        }

        // Create dependant assets and their corresponding edges
        if ((($direction == 'both') || ($direction == 'up')) && ($depth < $maxdepth)) {
            foreach ($this
                ->int_dependant_assets()
                ->when($aspect == 'confidentiality', function ($query) {
                    $query->where('inherit_confidentiality', true);
                })
                ->when($aspect == 'integrity', function ($query) {
                    $query->where('inherit_integrity', true);
                })
                ->when($aspect == 'availability', function ($query) {
                    $query->where('inherit_availability', true);
                })
                ->withPivot(['description'])->get() as $dependantAsset) {
                // If the dependant asset is already in the graph, skip it
                $createVertex = true;
                foreach ($graph->getVertices() as $vertex) {
                    if ('asset_'.$dependantAsset->id == $vertex->getAttribute('id')) {
                        $createVertex = false;
                        break;
                    }
                }

                if ($createVertex) {
                    $dependantAsset->dependencyGraph($depth + 1, $maxdepth, $graph, 'up', $aspect, $highlightInfoClass);
                }

                // Create edge
                foreach ($graph->getVertices() as $vertex) {
                    if ('asset_'.$dependantAsset->id == $vertex->getAttribute('id')) {
                        $edge = $graph->createEdgeUndirected($vertex, $currentAsset);
                        if ($dependantAsset->pivot->description) {
                            $edge->setAttribute('graphviz.label', ' '.$dependantAsset->pivot->description.' ');
                        }
                        $edge->setAttribute('graphviz.fontsize', $edgefontsize);
                        $edge->setAttribute('graphviz.fontname', $font);
                        break;
                    }
                }
            }
        }

        if ((($direction == 'both') || ($direction == 'down')) && ($depth < $maxdepth)) {
            foreach ($this
                ->int_depending_assets()
                ->when($aspect == 'confidentiality', function ($query) {
                    $query->where('inherit_confidentiality', true);
                })
                ->when($aspect == 'integrity', function ($query) {
                    $query->where('inherit_integrity', true);
                })
                ->when($aspect == 'availability', function ($query) {
                    $query->where('inherit_availability', true);
                })
                ->withPivot(['description'])->get() as $dependantAsset) {
                // If the dependant asset is already in the graph, skip it
                $createVertex = true;
                foreach ($graph->getVertices() as $vertex) {
                    if ('asset_'.$dependantAsset->id == $vertex->getAttribute('id')) {
                        $createVertex = false;
                        break;
                    }
                }

                if ($createVertex) {
                    $dependantAsset->dependencyGraph($depth + 1, $maxdepth, $graph, 'down', $aspect, $highlightInfoClass);
                }

                // Create edge
                foreach ($graph->getVertices() as $vertex) {
                    if ('asset_'.$dependantAsset->id == $vertex->getAttribute('id')) {
                        $edge = $graph->createEdgeUndirected($currentAsset, $vertex);
                        if ($dependantAsset->pivot->description) {
                            $edge->setAttribute('graphviz.label', ' '.$dependantAsset->pivot->description.' ');
                        }
                        $edge->setAttribute('graphviz.fontsize', '10');
                        break;
                    }
                }
            }
        }

        if ($depth == 0) {
            $graphviz = new GraphViz;
            $graphviz->setFormat('svg');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            return $graphviz->createImageHtml($graph);
        } else {
            return $graph;
        }
    }
}
