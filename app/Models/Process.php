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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SimpleXMLElement;

class Process extends Model
{
    use DefersRelationAttributeSync, HasCustomProperties, HasMessages, HasNotifications, HasTags;

    public static function getPrettyName($plural = false)
    {
        if ($plural) {
            return __('Processes');
        } else {
            return __('Process');
        }
    }

    /* Retrieve status for the entire collection of objects */
    public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
    {
        $retval = [];

        // Don't report if user cannot perform any changes anyway
        if (($user != null) && $user->cannot('update', Process::class)) {
            return [];
        }

        $retval = [];

        $countWithoutAssignment = self::query()
            ->when($department, fn (Builder $q) => $q->where('department_id', $department->id))
            ->whereNull('responsible_user_id')
            ->count();

        $scope = self::query()
            ->when($department, fn (Builder $q) => $q->where('department_id', $department->id))
            ->when($user, fn (Builder $q) => $q->where('responsible_user_id', $user->id));

        $uncharted = (clone $scope)
            ->where(function (Builder $q) {
                $q->whereNull('publishedbpmn')->orWhere('publishedbpmn', '');
            })
            ->count();

        // Process has at least one activity missing assignment
        $missingActivityAssignment = (clone $scope)
            ->whereHas('int_process_activities', function (Builder $pa) {
                $pa->whereNull('accountable_role_id')
                    ->orWhere(function (Builder $q) {
                        $q->whereNull('responsible_role_id')
                            ->whereDoesntHave('int_suppliers');
                    });
            })
            ->count();

        $canIndex = ((($user != null) && $user->can('index', get_called_class())) ||
           (($user == null) && (auth()->user() != null) && auth()->user()->can('index', get_called_class())));
        $url = $canIndex ? url()->query('/inventory/processes') : null;

        if (! $personalOnly && $countWithoutAssignment) {
            $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => Process::getPrettyName($countWithoutAssignment > 1).' '.__('without assignment'), 'url' => $url];
        }

        if ($uncharted) {
            $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $uncharted, 'text' => Process::getPrettyName($uncharted > 1).' '.__('without process chart'), 'url' => $url];
        }

        if ($missingActivityAssignment) {
            $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $missingActivityAssignment, 'text' => Process::getPrettyName($missingActivityAssignment > 1).' '.__('without activities being assigned'), 'url' => $url];
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

        static::saving(function ($model) {
            Validator::make($model->toArray(), $model->getValidationRules())->validate();

            if ($model->name != htmlspecialchars($model->name)) {
                abort(400, $model->name.' '.__('is not a valid name. It contains special characters which are not allowed.'));
            }

            if (($model->id > 0) && ($model->getOriginal('name') != $model->name)) {
                // Update references in affected processes
                foreach ($model->int_linking_processes as $proc) {
                    // Update BPMN
                    $proc->int_rename_processobject($model, $model->getOriginal('name'), $model->name);
                }
            }
        });

        // Prevent deletion if there are dependant items
        static::deleting(function ($model) {
            if (count($model->int_linking_processes) > 0) {
                abort(400, __('Cannot delete a process which have other processes linking to it'));
            }
        });

        // Update referred bpmn charts on name change
        static::updated(function ($model) {
            if ($model->wasChanged('isstartprocess') && $model->isstartprocess) {
                Process::where('id', '<>', $model->id)->update(['isstartprocess' => false]);
            }
        });

        static::created(function ($model) {
            if ($model->isstartprocess) {
                Process::where('id', '<>', $model->id)->update(['isstartprocess' => false]);
            }
        });

    }

    /**
     * Appended attributes
     */
    protected $appends = [
        'access',
        'process_informationtypes',
        'process_assets',
        'process_linkingprocesses',
        'legal_basises',
        'riskcount',
        'findingcount',
        'tags',
        'messagecount',
        'unclassifiedcount',
        'undecidedcount',
        'status',
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

        if (! $this->publishedbpmn) {
            return ['icon' => 'warning', 'level' => 'danger', 'text' => __('No process chart has been published')];
        }

        if (($this->getUnclassifiedcountAttribute() > 0) ||
           ($this->getUndecidedcountAttribute() > 0)) {
            if ((auth()->user() != null) && (auth()->user()->id == $this->responsible_user_id)) {
                return ['icon' => 'warning', 'level' => 'danger', 'text' => __('There are tasks that needs to be classified and/or assigned responsbility')];
            } else {
                return ['icon' => 'warning', 'level' => 'warning', 'text' => __('There are tasks that needs to be classified and/or assigned responsbility')];
            }
        }

        if ($this->isstartprocess) {
            return ['icon' => 'play_circle', 'level' => 'info', 'text' => ''];
        }

        return ['icon' => 'check', 'level' => 'info', 'text' => ''];

    }

    public function getUnclassifiedcountAttribute()
    {
        return 0;
    }

    public function getUndecidedcountAttribute()
    {
        if (config('ledningssystemet.disable_staff')) {
            return 0;
        }

        if (array_key_exists('undecidedcount_db', $this->attributes)) {
            return intval($this->attributes['undecidedcount_db']);
        }

        return $this->int_process_activities()
            ->where(function (Builder $q) {
                $q->whereNull('accountable_role_id')
                    ->orWhere(function (Builder $x) {
                        $x->whereNull('responsible_role_id')
                            ->whereDoesntHave('int_suppliers');
                    });
            })
            ->count();
    }

    public function getTagsAttribute()
    {
        if ($this->relationLoaded('tags')) {
            return $this->getRelation('tags');
        }

        return Cache::rememberForever('Process.getTagsAttribute.'.$this->id, function () {
            return $this->tags()->get();
        });
    }

    public function getMessagecountAttribute()
    {
        if (array_key_exists('messages_count', $this->attributes)) {
            return intval($this->attributes['messages_count']);
        }

        return $this->messages()->count();
    }

    public function getProcessLinkingprocessesAttribute()
    {
        if ($this->relationLoaded('int_linking_processes')) {
            return $this->getRelation('int_linking_processes')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Process.getProcessLinkingprocessesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_linking_processes as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function getProcessInformationtypesAttribute()
    {
        if ($this->relationLoaded('int_information_types')) {
            return $this->getRelation('int_information_types')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Process.getProcessInformationtypesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_information_types as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function getProcessAssetsAttribute()
    {
        if ($this->relationLoaded('int_assets')) {
            return $this->getRelation('int_assets')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Process.getProcessAssetsAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_assets as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function getLegalBasisesAttribute()
    {
        if ($this->relationLoaded('int_legal_basises')) {
            return $this->getRelation('int_legal_basises')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('Process.getLegalBasisesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_legal_basises as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function setLegalBasisesAttribute($value)
    {
        $this->syncRelationAttribute('legalbasises', fn ($model) => $model->int_legal_basises()->sync(($value != null) ? $value : []));
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

    /**
     * The attributes that shall be visible during serialization.
     */
    protected $visible = [
        'access',
        'id',
        'name',
        'description',
        'department_id',
        'responsible_user_id',
        'isstartprocess',
        'created_at',
        'updated_at',
        'legalbasisdescription',
        'thirdcountrytransferdescription',
        'thirdcountrytransferprotectiondescription',
        'securitymeasuredescription',
        'process_informationtypes',
        'process_assets',
        'process_linkingprocesses',
        'legal_basises',
        'riskcount',
        'findingcount',
        'tags',
        'messagecount',
        'unclassifiedcount',
        'undecidedcount',
        'status',
        'dataprocessor',
        'data_processor_processing_activities',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'department_id',
        'isstartprocess',
        'responsible_user_id',
        'legalbasisdescription',
        'thirdcountrytransferdescription',
        'thirdcountrytransferprotectiondescription',
        'securitymeasuredescription',
        'legal_basises',
        'dataprocessor',
        'data_processor_processing_activities',
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
        'revert',
    ];

    /**
     * Index-function used for fetching multiple items via API
     */
    public static function index(User $user)
    {
        $authUser = auth()->user();
        $table = (new self)->getTable();
        $activityTable = (new ProcessActivity)->getTable();

        $search = trim((string) request()->input('search', ''));
        $showMyOnly = (intval(request()->input('showmyonly', 0)) === 1);
        $departmentId = intval(request()->input('department_id', -1));
        $tagId = intval(request()->input('tag_id', 0));
        $responsibleUserId = intval(request()->input('responsible_user_id', 0));
        $id = intval(request()->input('id', 0));
        $hideChecked = (bool) request()->input('hidechecked', 0);
        $disableStaff = config('ledningssystemet.disable_staff');

        $hasCustomPropertyFilter = count(array_filter(
            array_keys(request()->all()),
            static fn ($key) => (strpos($key, 'customproperty_') === 0)
        )) > 0;

        $query = self::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'LIKE', '%'.$search.'%')
                        ->orWhere('description', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('tags', function (Builder $tagQuery) use ($search) {
                            $tagQuery->where('name', 'LIKE', '%'.$search.'%');
                        });
                });
            })
            ->when($showMyOnly && $authUser, function (Builder $query) use ($authUser) {
                $query->where('responsible_user_id', $authUser->id);
            })
            ->when(request()->has('department_id'), function (Builder $query) use ($departmentId, $authUser) {
                if ($departmentId > 0) {
                    $query->where('department_id', $departmentId);
                } elseif ($departmentId === 0 && $authUser) {
                    $myDeps = [];
                    foreach ($authUser->int_departments as $dep) {
                        $myDeps[] = $dep->id;
                    }
                    $query->whereIn('department_id', $myDeps);
                }
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
            });

        if ($hasCustomPropertyFilter) {
            self::getIndexQuery($query);
        }

        $query
            ->select($table.'.*')
            ->with([
                'tags',
                'int_linking_processes:id,name',
                'int_information_types:id,name',
                'int_assets:id,name',
            ])
            ->withCount([
                'messages',
                'int_risks',
                'int_findings',
            ]);

        if (! $disableStaff) {
            $query->withCount([
                'int_process_activities as undecidedcount_db' => function (Builder $pa) {
                    $pa->whereNull('accountable_role_id')
                        ->orWhere(function (Builder $x) {
                            $x->whereNull('responsible_role_id')
                                ->whereDoesntHave('int_suppliers');
                        });
                },
            ]);
        }

        if ($hideChecked && (new self)->status) {
            $query->where(function (Builder $q) use ($disableStaff) {
                $q->whereNull('responsible_user_id')
                    ->orWhere(function (Builder $x) {
                        $x->whereNull('publishedbpmn')
                            ->orWhere('publishedbpmn', '');
                    });

                if (! $disableStaff) {
                    $q->orWhereHas('int_process_activities', function (Builder $pa) {
                        $pa->whereNull('accountable_role_id')
                            ->orWhere(function (Builder $x) {
                                $x->whereNull('responsible_role_id')
                                    ->whereDoesntHave('int_suppliers');
                            });
                    });
                }
            });

            return $query->orderBy('name')->get();
        }

        return $query->orderBy('name')->paginate();
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
                Rule::unique('processes')->ignore($this->id),
            ],
            'responsible_user_id' => 'nullable|exists:App\Models\User,id',
            'department_id' => 'nullable|exists:App\Models\Department,id',
        ];
    }

    /**
     * Get the process activities
     */
    public function int_process_activities(): HasMany
    {
        return $this->hasMany(ProcessActivity::class);
    }

    /**
     * Get the process hrefs
     */
    public function int_process_hrefs(): HasMany
    {
        return $this->hasMany(ProcessHref::class);
    }

    /**
     * Get the department associated with the process.
     */
    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the assets associated with the process
     */
    public function int_assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_information_type')->distinct();
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get the customers associated with the process
     */
    public function int_customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_process');
    }

    /**
     * Get the information types associated with the process
     */
    public function int_information_types(): BelongsToMany
    {
        return $this->belongsToMany(InformationType::class, 'asset_information_type')->distinct();
    }

    /**
     * Get processes linked to by this process
     */
    public function int_linked_processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'process_links', 'process_id', 'linked_process_id')->distinct();
    }

    /**
     * Get processes linking to this process
     */
    public function int_linking_processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'process_links', 'linked_process_id', 'process_id')->distinct();
    }

    /**
     * Documents
     */
    public function int_library_documents(): BelongsToMany
    {
        return $this->belongsToMany(LibraryDocument::class, 'library_document_processes', 'process_id', 'library_document_id');
    }

    /**
     * Legal basises
     */
    public function int_legal_basises(): BelongsToMany
    {
        return $this->belongsToMany(LegalBasis::class, 'legal_basis_process', 'process_id', 'legal_basis_id');
    }

    /*
     * Get published bpmn viewbox
     */
    public function getViewbox()
    {
        $retval = ['left' => null, 'right' => null, 'top' => null, 'bottom' => null];

        // Check if published
        if (($this->publishedbpmn != null) && ($this->publishedbpmn != '')) {
            /* Load BPMN chart as XML */
            $xmlobj = new SimpleXMLElement($this->publishedbpmn);

            // Register namespaces
            $xmlobj->registerXPathNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $xmlobj->registerXPathNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');
            $xmlobj->registerXPathNamespace('bpmndi', 'http://www.omg.org/spec/BPMN/20100524/DI');
            $xmlobj->registerXPathNamespace('dc', 'http://www.omg.org/spec/DD/20100524/DC');

            foreach ($xmlobj->xpath('//*[@x|@y]') as $node) {
                $x = isset($node['x']) ? intval($node['x']) : null;
                $y = isset($node['y']) ? intval($node['y']) : null;
                $width = isset($node['width']) ? intval($node['width']) : 0;
                $height = isset($node['height']) ? intval($node['height']) : 0;

                if ($x != null) {
                    if ($retval['left'] == null) {
                        $retval['left'] = $x;
                    } else {
                        $retval['left'] = min($retval['left'], $x);
                    }

                    if ($retval['right'] == null) {
                        $retval['right'] = $x + $width;
                    } else {
                        $retval['right'] = max($retval['right'], $x + $width);
                    }
                }

                if ($y != null) {
                    if ($retval['top'] == null) {
                        $retval['top'] = $y;
                    } else {
                        $retval['top'] = min($retval['top'], $y);
                    }

                    if ($retval['bottom'] == null) {
                        $retval['bottom'] = $y + $height;
                    } else {
                        $retval['bottom'] = max($retval['bottom'], $y + $height);
                    }

                }
            }
        }

        return $retval;
    }

    /**
     * Get risks associated with this process
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
     * Get metrics
     */
    public function int_process_performance_metrics(): BelongsToMany
    {
        return $this->belongsToMany(ProcessPerformanceMetric::class);
    }

    /**
     * Rename callback used for updating references in BPMN chart when objects are renamed
     */
    public function int_rename_processobject($object, $oldname, $newname)
    {
        // Validate new name
        if ($newname != htmlspecialchars($newname)) {
            throw new \Exception($newname.' '.__('is not a valid name. It contains special characters which are not allowed.'));
        }

        $bpmn = $this->bpmn ? simplexml_load_string($this->bpmn) : null;
        $publishedbpmn = $this->publishedbpmn ? simplexml_load_string($this->publishedbpmn) : null;

        $hitcount = 0;
        switch ($object::class) {
            case 'App\Models\Asset':
                if ($publishedbpmn) {
                    foreach ($publishedbpmn->xpath('//bpmn:dataStoreReference[@name="'.str_replace('"', '&quot;', $oldname).'"]/@name') as $node) {
                        $node[0] = $newname;
                        $hitcount++;
                    }
                }

                if ($bpmn) {
                    foreach ($bpmn->xpath('//bpmn:dataStoreReference[@name="'.str_replace('"', '\\"', $oldname).'"]/@name') as $node) {
                        $node[0] = $newname;
                    }
                }
                break;
            case 'App\Models\InformationType':
                if ($publishedbpmn) {
                    foreach ($publishedbpmn->xpath('//bpmn:dataObjectReference[@name="'.str_replace('"', '&quot;', $oldname).'"]/@name') as $node) {
                        $node[0] = $newname;
                        $hitcount++;
                    }
                }

                if ($bpmn) {
                    foreach ($bpmn->xpath('//bpmn:dataObjectReference[@name="'.str_replace('"', '\\"', $oldname).'"]/@name') as $node) {
                        $node[0] = $newname;
                    }
                }
                break;
            case 'App\Models\Process':
                if ($publishedbpmn) {
                    foreach ($publishedbpmn->xpath('//bpmn:subProcess[@name="'.str_replace('"', '&quot;', $oldname).'"]/@name') as $node) {
                        $node[0] = $newname;
                        $hitcount++;
                    }
                }

                if ($bpmn) {
                    foreach ($bpmn->xpath('//bpmn:subProcess[@name="'.str_replace('"', '\\"', $oldname).'"]/@name') as $node) {
                        $node[0] = $newname;
                    }
                }
                break;
            default:
                throw new \Exception(__('Renaming objects of type :type is not supported', ['type' => $object::class]));
        }
        if ($hitcount == 0) {
            throw new \Exception(__('Failed to find any references to the renamed object in the BPMN chart'));
        }

        if ($bpmn) {
            $this->bpmn = $bpmn->asXML();
        }

        if ($publishedbpmn) {
            $this->publishedbpmn = $publishedbpmn->asXML();
        }

        $this->save();
    }

    /**
     * Revert to last published version of BPMN-chart
     */
    public function revert()
    {
        // Ensure user have edit rights
        if (auth()->user()->cannot('update', $this)) {
            abort(403);
        }

        $this->bpmn = $this->publishedbpmn;
        $this->save();

        exit(json_encode([]));
    }
}
