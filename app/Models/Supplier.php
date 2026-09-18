<?php

namespace App\Models;

use App\Models\Concerns\DefersRelationAttributeSync;
use App\Traits\HasCustomProperties;
use App\Traits\HasMessages;
use App\Traits\HasNotifications;
use App\Traits\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class Supplier extends Model
{
    use DefersRelationAttributeSync, HasCustomProperties, HasMessages, HasNotifications, HasTags;

    public static function getPrettyName($plural = false)
    {
        if ($plural) {
            return __('Suppliers');
        } else {
            return __('Supplier');
        }
    }

    /* Retrieve status for the entire collection of objects */
    public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
    {
        if (config('ledningssystemet.disable_supplier')) {
            return [];
        }

        if ($department != null) {
            return [];
        }

        if (($user != null) && $user->cannot('update', Supplier::class)) {
            return [];
        }

        $retval = [];
        $url = ((($user != null) && $user->can('index', get_called_class())) ||
           (($user == null) && (auth()->user() != null) && auth()->user()->can('index', get_called_class())))
           ? url()->query('/inventory/suppliers')
           : null;

        // Without assignment – no user filter
        $countWithoutAssignment = Supplier::whereNull('responsible_user_id')->count();
        if (! $personalOnly && $countWithoutAssignment) {
            $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => Supplier::getPrettyName($countWithoutAssignment > 1).' '.__('without assignment'), 'url' => $url];
        }

        $table = (new self)->getTable();
        $scope = Supplier::query()->when($user, fn (Builder $q) => $q->where('responsible_user_id', $user->id));
        $totalCategories = SupplierCategory::count();

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
            if (! isset($overdueSupplierIds[$row->supplier_id]) &&
               strtotime($row->reassessment_interval, strtotime($row->updated_at)) < time()) {
                $overdueSupplierIds[$row->supplier_id] = true;
            }
        }
        $numoverdue = count($overdueSupplierIds);

        if ($uncategorized) {
            $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $uncategorized, 'text' => Supplier::getPrettyName($uncategorized > 1).' '.__('without categorization'), 'url' => $url];
        }

        if ($unevaluated) {
            $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $unevaluated, 'text' => Supplier::getPrettyName($unevaluated > 1).' '.__('without evaluation'), 'url' => $url];
        }

        if ($notapproved) {
            $retval[] = ['level' => 'warning', 'count' => $notapproved, 'text' => Supplier::getPrettyName($notapproved > 1).' '.__('with failed requirements'), 'url' => $url];
        }

        if ($numoverdue) {
            $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $numoverdue, 'text' => Supplier::getPrettyName($numoverdue > 1).' '.__('needs re-evaluation'), 'url' => $url];
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

        // Set initial categories when created
        static::created(function ($model) {
            foreach (SupplierCategory::whereNotNull('defaultvalue')->get() as $cat) {
                DB::table('supplier_supplier_category')->insert(
                    [
                        'supplier_id' => $model->id,
                        'supplier_category_id' => $cat->id,
                        'applicable' => $cat->defaultvalue,
                        'updated_by_name' => (auth()->user() != null) ? auth()->user()->name : 'System',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );
            }
        });
    }

    /**
     * Appended attributes
     */
    protected $appends = [
        'access',
        'assets',
        'processactivities',
        'riskcount',
        'findingcount',
        'tags',
        'messagecount',
        'status',
        'supplier_categories',
        'files',
        'agreements',
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

        $totalCategories = Cache::rememberForever('SupplierCategory.count', fn () => SupplierCategory::count());
        $isOwner = auth()->user() !== null && auth()->user()->id == $this->responsible_user_id;

        // Prefer precomputed selectSub value, then loaded relation, then query
        if (array_key_exists('supplier_category_count_db', $this->attributes)) {
            $assessmentCount = intval($this->attributes['supplier_category_count_db']);
        } elseif ($this->relationLoaded('int_supplier_category_assessments')) {
            $assessmentCount = $this->int_supplier_category_assessments->count();
        } else {
            $assessmentCount = $this->int_supplier_category_assessments()->count();
        }

        if ($assessmentCount != $totalCategories) {
            return ['icon' => 'warning', 'level' => $isOwner ? 'danger' : 'warning', 'text' => __('The supplier has not been categorized')];
        }

        $hasUnevaluated = array_key_exists('has_unevaluated_requirements_db', $this->attributes)
           ? intval($this->attributes['has_unevaluated_requirements_db']) > 0
           : $this->int_supplier_requirements()->whereNull('supplier_supplier_requirement.id')->exists();

        if ($hasUnevaluated) {
            return ['icon' => 'warning', 'area' => 'evaluation', 'level' => $isOwner ? 'danger' : 'warning', 'text' => __('The supplier has not been evaluated')];
        }

        $hasFailed = array_key_exists('has_failed_requirements_db', $this->attributes)
           ? intval($this->attributes['has_failed_requirements_db']) > 0
           : $this->int_supplier_requirements()->where('satisfactory', false)->exists();

        if ($hasFailed) {
            return ['icon' => 'warning', 'area' => 'evaluation', 'level' => 'warning', 'text' => __('The supplier does not fulfil mandatory requirements')];
        }

        // Overdue check – must stay in PHP due to strtotime() interval format
        foreach ($this->int_supplier_requirements()->where('supplier_requirements.reassessment', true)->whereNotNull('supplier_categories.reassessment_interval')->select(['supplier_supplier_requirement.updated_at', 'supplier_categories.reassessment_interval'])->get() as $req) {
            if (strtotime($req->reassessment_interval, strtotime($req->updated_at)) < time()) {
                return ['icon' => 'warning', 'area' => 'evaluation', 'level' => $isOwner ? 'danger' : 'warning', 'text' => __('Supplier re-evaluation is overdue')];
            }
        }

        return ['icon' => 'check', 'level' => 'info', 'text' => ''];
    }

    public function getFilesAttribute()
    {
        return DB::table('files')->where('object_type', $this::class)->where('object_id', $this->id)->select(['id', 'filename', 'name', 'description', 'contenttype', 'contentlength'])->get();
    }

    public function getAgreementsAttribute()
    {
        return $this->int_agreements;
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

    public function getAssetsAttribute()
    {
        $retval = [];
        foreach ($this->int_assets as $obj) {
            $retval[] = ['id' => $obj->id, 'name' => $obj->name];
        }

        return $retval;
    }

    public function getProcessactivitiesAttribute()
    {
        $retval = [];
        foreach ($this->int_process_activities as $obj) {
            $retval[] = ['id' => $obj->id, 'name' => $obj->name];
        }

        return $retval;
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

    public function getSupplierCategoriesAttribute()
    {
        return $this->int_supplier_categories();
    }

    public function setSupplierCategoryAssessmentsAttribute($value)
    {
        $suppliercats = [];
        $value = is_array($value) ? $value : [];

        foreach (SupplierCategory::get() as $cat) {
            if (array_key_exists($cat->id, $value) && ($value[$cat->id] !== null) && ($value[$cat->id] !== '')) {
                $suppliercats[$cat->id] = [
                    'applicable' => (strtolower(''.$value[$cat->id]) == 'true'),
                    'updated_by_name' => auth()->user()->name,
                ];
            }
        }

        $this->syncRelationAttribute('suppliercategoryassessments', fn ($model) => $model->int_supplier_category_assessments()->sync($suppliercats));
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
        'processoragreementdescription',
        'dataprocessor',
        'assets',
        'processactivities',
        'riskcount',
        'findingcount',
        'tags',
        'messagecount',
        'status',
        'supplier_categories',
        'supplier_id',
        'external_supplier_id',
        'files',
        'agreements',
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
        'external_supplier_id',
        'processoragreementdescription',
        'dataprocessor',
        'supplier_category_assessments',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
    ];

    /**
     * The public actions available
     */
    public $actions = [
        'evaluation',
        'upload',
    ];

    /**
     * Index-function used for fetching multiple items via API
     */
    public static function index(User $user)
    {
        $table = (new self)->getTable();
        $search = trim((string) request()->input('search', ''));

        $query = self::query()
            ->select($table.'.*')
           // Antal kategorier som finns registrerade för denna leverantör
            ->selectSub(function ($sub) use ($table) {
                $sub->from('supplier_supplier_category as _ssc')
                    ->whereColumn('_ssc.supplier_id', $table.'.id')
                    ->selectRaw('COUNT(*)');
            }, 'supplier_category_count_db')
           // Har ofullständig utvärdering (applicable, men ingen evaluation-rad)
            ->selectSub(function ($sub) use ($table) {
                $sub->from('supplier_requirements as _sr')
                    ->join('supplier_categories as _sc', '_sc.id', '=', '_sr.supplier_category_id')
                    ->join('supplier_supplier_category as _ssc', function ($j) use ($table) {
                        $j->on('_ssc.supplier_category_id', '=', '_sc.id')
                            ->whereColumn('_ssc.supplier_id', $table.'.id')
                            ->where('_ssc.applicable', true);
                    })
                    ->leftJoin('supplier_supplier_requirement as _ssr', function ($j) use ($table) {
                        $j->on('_ssr.supplier_requirement_id', '=', '_sr.id')
                            ->whereColumn('_ssr.supplier_id', $table.'.id');
                    })
                    ->whereNull('_ssr.id')
                    ->selectRaw('CAST(COUNT(*) > 0 AS UNSIGNED)');
            }, 'has_unevaluated_requirements_db')
           // Har krav som inte uppfylls (satisfactory = false)
            ->selectSub(function ($sub) use ($table) {
                $sub->from('supplier_requirements as _sr2')
                    ->join('supplier_categories as _sc2', '_sc2.id', '=', '_sr2.supplier_category_id')
                    ->join('supplier_supplier_category as _ssc2', function ($j) use ($table) {
                        $j->on('_ssc2.supplier_category_id', '=', '_sc2.id')
                            ->whereColumn('_ssc2.supplier_id', $table.'.id')
                            ->where('_ssc2.applicable', true);
                    })
                    ->join('supplier_supplier_requirement as _ssr2', function ($j) use ($table) {
                        $j->on('_ssr2.supplier_requirement_id', '=', '_sr2.id')
                            ->whereColumn('_ssr2.supplier_id', $table.'.id');
                    })
                    ->where('_ssr2.satisfactory', false)
                    ->selectRaw('CAST(COUNT(*) > 0 AS UNSIGNED)');
            }, 'has_failed_requirements_db')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'LIKE', '%'.$search.'%')
                        ->orWhere('description', 'LIKE', '%'.$search.'%')
                        ->orWhere('external_supplier_id', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('tags', function (Builder $tagQuery) use ($search) {
                            $tagQuery->where('name', 'LIKE', '%'.$search.'%');
                        });
                });
            })
            ->when(intval(request()->input('showmyonly', 0)) == 1, function (Builder $query) {
                $query->where('responsible_user_id', auth()->user()->id);
            })
            ->when(intval(request()->input('tag_id', 0)) > 0, function (Builder $query) {
                $query->whereHas('tags', function (Builder $q) {
                    $q->where('tags.id', intval(request()->input('tag_id', 0)));
                });
            })
            ->when(intval(request()->input('supplier_category_id', 0)) > 0, function (Builder $query) {
                $query->whereHas('int_supplier_category_assessments', function (Builder $q) {
                    $q->where('supplier_categories.id', intval(request()->input('supplier_category_id', 0)))
                        ->where('supplier_supplier_category.applicable', true);
                });
            })
            ->when(intval(request()->input('responsible_user_id', 0)) > 0, function (Builder $query) {
                $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
            })
            ->when(intval(request()->input('id', 0)) > 0, function (Builder $query) use ($table) {
                $query->where($table.'.id', intval(request()->input('id', 0)));
            })
            ->when(count(array_filter(array_keys(request()->all()), fn ($var) => str_starts_with($var, 'customproperty_'))) > 0, function (Builder $query) {
                self::getIndexQuery($query);
            })
            ->with([
                'tags',
                'int_assets:id,supplier_id,name',
                'int_process_activities:id,name',
                'int_agreements',
                'int_supplier_category_assessments',
            ])
            ->withCount([
                'messages',
                'int_risks',
                'int_findings',
            ])
            ->orderBy('name');

        if (request()->input('hidechecked', 0) && (new self)->status) {
            return $query->get()->filter(fn ($item) => $item->status['level'] !== 'info');
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
                Rule::unique('suppliers')->ignore($this->id),
            ],
            'responsible_user_id' => 'nullable|exists:App\Models\User,id',
        ];
    }

    /**
     * Get the process activities associated with the object
     */
    public function int_process_activities(): BelongsToMany
    {
        return $this->belongsToMany(ProcessActivity::class);
    }

    /**
     * Get the assets associated with the object
     */
    public function int_assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'supplier_id');
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

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'supplier_id');
    }

    /**
     * Get supplier categories
     */
    public function int_supplier_categories()
    {
        $decided = $this->relationLoaded('int_supplier_category_assessments')
           ? $this->getRelation('int_supplier_category_assessments')
           : $this->int_supplier_category_assessments()->get();

        $allCategories = Cache::rememberForever('SupplierCategory.all_ordered', fn () => SupplierCategory::orderBy('name')->get());

        $retval = [];
        foreach ($allCategories as $cat) {
            $assessmentObject = $decided->firstWhere('id', $cat->id);
            $retval[] = [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'applicable' => $assessmentObject ? $assessmentObject->pivot->applicable : null,
                'updated_by_name' => $assessmentObject ? $assessmentObject->pivot->updated_by_name : null,
                'updated_at' => $assessmentObject ? $assessmentObject->pivot->updated_at : null,
            ];
        }

        return $retval;
    }

    /**
     * Get supplier category assessments
     */
    public function int_supplier_category_assessments(): BelongsToMany
    {
        return $this->belongsToMany(SupplierCategory::class, 'supplier_supplier_category')->withPivot('applicable', 'updated_by_name', 'updated_at');
    }

    /**
     * Get all requirements, including status of evaluation
     */
    public function int_supplier_requirements()
    {
        // Get all requirements
        return SupplierRequirement::where('suppliers.id', $this->id)
            ->where('supplier_supplier_category.applicable', true)
            ->join('supplier_categories', 'supplier_categories.id', '=', 'supplier_requirements.supplier_category_id')
            ->join('supplier_supplier_category', 'supplier_supplier_category.supplier_category_id', '=', 'supplier_categories.id')
            ->join('suppliers', 'supplier_supplier_category.supplier_id', '=', 'suppliers.id')
            ->leftJoin('supplier_supplier_requirement', function (JoinClause $join) {
                $join->on('supplier_supplier_requirement.supplier_requirement_id', '=', 'supplier_requirements.id')
                    ->on('supplier_supplier_requirement.supplier_id', '=', 'suppliers.id');
            })
            ->select(
                'supplier_requirements.*',
                'supplier_supplier_requirement.id as supplier_supplier_requirement_id',
                'supplier_supplier_requirement.note as note',
                'supplier_supplier_requirement.satisfactory as satisfactory',
                'supplier_supplier_requirement.updated_by_name as evaluated_by_name',
                'supplier_supplier_requirement.updated_at as evaluated_at',
            )
            ->orderBy('supplier_requirements.name');

    }

    public function evaluation()
    {
        // Get all evaluations
        if (request()->method() == 'GET') {
            if (auth()->user()->cannot('index', Supplier::class)) {
                abort(403);
            }

            // Get all requirements
            return $this->int_supplier_requirements()->get();
        } elseif (request()->method() == 'POST') {
            if (auth()->user()->cannot('update', Supplier::class)) {
                abort(403);
            }

            $obj = DB::table('supplier_supplier_requirement')
                ->where('supplier_requirement_id', request()->input('requirement_id'))
                ->where('supplier_id', request()->input('supplier_id'))
                ->first();

            if ($obj == null) {
                DB::table('supplier_supplier_requirement')->insert(
                    ['supplier_requirement_id' => request()->input('requirement_id'),
                        'supplier_id' => request()->input('supplier_id'),
                        'note' => request()->input('note'),
                        'satisfactory' => (intval(request()->input('satisfactory', 0)) == 1),
                        'updated_by_name' => auth()->user()->name,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );
            } else {
                DB::table('supplier_supplier_requirement')->where('id', $obj->id)->update(
                    [
                        'note' => request()->input('note'),
                        'satisfactory' => (intval(request()->input('satisfactory', 0)) == 1),
                        'updated_by_name' => auth()->user()->name,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );
            }

            return [];
        }
    }
}
