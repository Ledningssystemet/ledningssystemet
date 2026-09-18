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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InformationType extends Model
{
    use DefersRelationAttributeSync, HasCustomProperties, HasMessages, HasNotifications, HasTags;

    public static function getPrettyName($plural = false)
    {
        if ($plural) {
            return __('Information types');
        } else {
            return __('Information type');
        }
    }

    /* Retrieve status for the entire collection of objects */
    public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
    {
        if ($department != null) {
            return [];
        }

        if (($user != null) && $user->cannot('update', InformationType::class)) {
            return [];
        }

        $retval = [];
        $url = ((($user != null) && $user->can('index', get_called_class())) ||
           (($user == null) && (auth()->user() != null) && auth()->user()->can('index', get_called_class())))
           ? url()->query('/inventory/informationtypes')
           : null;

        $countWithoutAssignment = InformationType::whereNull('responsible_user_id')->count();
        if (! $personalOnly && $countWithoutAssignment) {
            $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => InformationType::getPrettyName($countWithoutAssignment > 1).' '.__('without assignment'), 'url' => $url];
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
                $processes = [];

                // Calculate affected bpmn charts
                foreach ($model->int_process_activities as $pa) {
                    $processes[$pa->int_process->id] = $pa->int_process;
                }

                // Update references in affected processes
                foreach ($processes as $proc) {
                    // Update BPMN
                    $proc->int_rename_processobject($model, $model->getOriginal('name'), $model->name);
                }
            }
        });

        // Prevent deletion of information type in process
        static::deleting(function ($model) {
            if ($model->int_process_activities()->exists()) {
                throw new SoftException(__('You cannot delete an information type which is part of a process'));
            }

        });

    }

    /**
     * Appended attributes
     */
    protected $appends = [
        'access',
        'processes',
        'assets',
        'data_subject_categories',
        'data_categories',
        'recipient_categories',
        'riskcount',
        'classified',
        'tags',
        'messagecount',
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
        return Cache::rememberForever('InformationType.getStatusAttribute.'.$this->id, function () {
            if (! $this->responsible_user_id) {
                return ['icon' => 'warning', 'level' => 'danger', 'text' => __('A responsible user has not been assigned')];
            }

            // Billiga kolumnchecks först, undvik onödig klassificeringsfråga
            if (($this->confidentiality_class_id == null) ||
               ($this->integrity_class_id == null) ||
               ($this->availability_class_id == null) ||
               ! $this->classified) {
                return ['icon' => 'warning', 'level' => 'warning', 'text' => __('The information type has not been classified')];
            }

            $hasSensitiveData = $this->relationLoaded('int_data_categories')
               ? $this->getRelation('int_data_categories')->contains(fn ($cat) => (bool) $cat->sensitive)
               : $this->int_data_categories()->where('sensitive', true)->exists();

            if ($hasSensitiveData) {
                $processes = [];

                if ($this->relationLoaded('int_process_activities')) {
                    foreach ($this->getRelation('int_process_activities') as $pa) {
                        $proc = $pa->relationLoaded('int_process') ? $pa->getRelation('int_process') : $pa->int_process;
                        if ($proc) {
                            $processes[$proc->id] = $proc;
                        }
                    }
                } else {
                    foreach ($this->int_processes() as $proc) {
                        $processes[$proc->id] = $proc;
                    }
                }

                foreach ($processes as $proc) {
                    $hasNonSensitiveLegalBasis = $proc->relationLoaded('int_legal_basises')
                       ? $proc->getRelation('int_legal_basises')->contains(fn ($lb) => ! $lb->sensitive)
                       : $proc->int_legal_basises()->where('sensitive', false)->exists();

                    if ($hasNonSensitiveLegalBasis) {
                        return ['icon' => 'warning', 'level' => 'warning', 'text' => __('The information type contains sensitive personal data but is used in a process without a legal basis allowing sensitive personal data')];
                    }
                }
            }

            $isUsedInProcess = $this->relationLoaded('int_process_activities')
               ? $this->getRelation('int_process_activities')->isNotEmpty()
               : $this->int_process_activities()->exists();

            if (! $isUsedInProcess) {
                return ['icon' => 'warning', 'level' => 'warning', 'text' => __('This information type is not used within any processes and should be removed or assigned to a process')];
            }

            return ['icon' => 'check', 'level' => 'info', 'text' => ''];
        });
    }

    public function getTagsAttribute()
    {
        if ($this->relationLoaded('tags')) {
            return $this->getRelation('tags')->each->setAppends([]);
        }

        return Cache::rememberForever('InformationType.getTagsAttribute.'.$this->id, function () {
            return $this->tags()->get()->each->setAppends([]);
        });
    }

    public function getMessagecountAttribute()
    {
        if (array_key_exists('messages_count', $this->attributes)) {
            return intval($this->attributes['messages_count']);
        }

        return $this->relationLoaded('messages')
           ? $this->getRelation('messages')->count()
           : $this->messages()->count();
    }

    public function getProcessesAttribute()
    {
        if ($this->relationLoaded('int_process_activities')) {
            $retval = [];
            foreach ($this->getRelation('int_process_activities') as $pa) {
                $proc = $pa->relationLoaded('int_process') ? $pa->getRelation('int_process') : $pa->int_process;
                if ($proc) {
                    $retval[$proc->id] = ['id' => $proc->id, 'name' => $proc->name];
                }
            }

            return array_values($retval);
        }

        return Cache::rememberForever('InformationType.getProcessesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_processes() as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function getAssetsAttribute()
    {
        if ($this->relationLoaded('int_assets')) {
            return $this->getRelation('int_assets')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('InformationType.getAssetsAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_assets as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function setAssetsAttribute($value)
    {
        $this->syncRelationAttribute('assets', fn ($model) => $model->int_assets()->sync(($value != null) ? $value : []));
    }

    public function getDataSubjectCategoriesAttribute()
    {
        if ($this->relationLoaded('int_subject_categories')) {
            return $this->getRelation('int_subject_categories')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('InformationType.getDataSubjectCategoriesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_subject_categories as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function setDataSubjectCategoriesAttribute($value)
    {
        $this->syncRelationAttribute('datasubjectcategories', fn ($model) => $model->int_subject_categories()->sync(($value != null) ? $value : []));
    }

    public function getDataCategoriesAttribute()
    {
        if ($this->relationLoaded('int_data_categories')) {
            return $this->getRelation('int_data_categories')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('InformationType.getDataCategoriesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_data_categories as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function setDataCategoriesAttribute($value)
    {
        $this->syncRelationAttribute('datacategories', fn ($model) => $model->int_data_categories()->sync(($value != null) ? $value : []));
    }

    public function getRecipientCategoriesAttribute()
    {
        if ($this->relationLoaded('int_recipient_categories')) {
            return $this->getRelation('int_recipient_categories')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        return Cache::rememberForever('InformationType.getRecipientCategoriesAttribute.'.$this->id, function () {
            $retval = [];
            foreach ($this->int_recipient_categories as $obj) {
                $retval[] = ['id' => $obj->id, 'name' => $obj->name];
            }

            return $retval;
        });
    }

    public function setRecipientCategoriesAttribute($value)
    {
        $this->syncRelationAttribute('recipientcategories', fn ($model) => $model->int_recipient_categories()->sync(($value != null) ? $value : []));
    }

    public function getRiskcountAttribute()
    {
        if (array_key_exists('int_risks_count', $this->attributes)) {
            return intval($this->attributes['int_risks_count']);
        }

        return $this->int_risks()->count();
    }

    public function getClassifiedAttribute()
    {
        return $this->isClassified();
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
        'confidentiality_class_id',
        'integrity_class_id',
        'availability_class_id',
        'confidentiality_ground_id',
        'diary_id',
        'retention',
        'mtd',
        'rpo',
        'piidescription',
        'processes',
        'assets',
        'data_subject_categories',
        'data_categories',
        'recipient_categories',
        'riskcount',
        'classified',
        'tags',
        'messagecount',
        'status',
        'archivingdescription',
        'sortinginformation',
        'archivemedia',
        'archiveshippingtime',
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
        'confidentiality_class_id',
        'integrity_class_id',
        'availability_class_id',
        'confidentiality_ground_id',
        'diary_id',
        'retention',
        'piidescription',
        'archivingdescription',
        'sortinginformation',
        'archivemedia',
        'archiveshippingtime',
        'assets',
        'data_subject_categories',
        'data_categories',
        'recipient_categories',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
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
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'LIKE', '%'.$search.'%')
                        ->orWhere('description', 'LIKE', '%'.$search.'%')
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
            ->when(intval(request()->input('confidentiality_class_id', 0)) > 0, function (Builder $query) {
                $query->where('confidentiality_class_id', intval(request()->input('confidentiality_class_id', 0)));
            })
            ->when(intval(request()->input('integrity_class_id', 0)) > 0, function (Builder $query) {
                $query->where('integrity_class_id', intval(request()->input('integrity_class_id', 0)));
            })
            ->when(intval(request()->input('availability_class_id', 0)) > 0, function (Builder $query) {
                $query->where('availability_class_id', intval(request()->input('availability_class_id', 0)));
            })
            ->when(intval(request()->input('process_id', 0)) > 0, function (Builder $query) {
                $query->whereHas('int_process_activities.int_process', function (Builder $q) {
                    $q->where('processes.id', intval(request()->input('process_id', 0)));
                });
            })
            ->when(intval(request()->input('responsible_user_id', 0)) > 0, function (Builder $query) {
                $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
            })
            ->when(intval(request()->input('id', 0)) > 0, function (Builder $query) use ($table) {
                $query->where($table.'.id', intval(request()->input('id', 0)));
            })
            ->when(
                count(array_filter(array_keys(request()->all()), fn ($key) => str_starts_with($key, 'customproperty_'))) > 0,
                fn (Builder $query) => self::getIndexQuery($query)
            )
            ->when(request()->input('hidechecked', 0), function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->whereNull('responsible_user_id')
                        ->orWhereNull('confidentiality_class_id')
                        ->orWhereNull('integrity_class_id')
                        ->orWhereNull('availability_class_id')
                        ->orWhere(function (Builder $sq) {
                            $sq->whereHas('int_data_categories', fn (Builder $dc) => $dc->where('sensitive', true))
                                ->whereHas('int_process_activities.int_process', function (Builder $p) {
                                    $p->whereHas('int_legal_basises', fn (Builder $lb) => $lb->where('sensitive', false));
                                });
                        })
                        ->orWhereDoesntHave('int_process_activities');
                });
            })
           // Eager-load relationer som accessorer använder
            ->with([
                'tags',
                'messages',
                'int_assets',
                'int_data_categories',
                'int_process_activities.int_process.int_legal_basises',
            ])
           // Preload count-fält för accessorer
            ->withCount([
                'messages',
                'int_risks',
            ])
            ->orderBy('name');

        if (request()->input('hidechecked', 0)) {
            return $query->get();
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
                Rule::unique('information_types')->ignore($this->id),
            ],
            'responsible_user_id' => 'nullable|exists:App\Models\User,id',
            'confidentiality_ground_id' => 'nullable|exists:App\Models\ConfidentialityGround,id',
            'diary_id' => 'nullable|exists:App\Models\Diary,id',
        ];
    }

    /**
     * Get the ProcessActivities associated with the object
     */
    public function int_process_activities(): BelongsToMany
    {
        return $this->belongsToMany(ProcessActivity::class);
    }

    /**
     * Get the Processes associated with the object
     */
    public function int_processes()
    {
        $processes = [];
        foreach ($this->int_process_activities as $pa) {
            $processes[$pa->int_process->id] = $pa->int_process;
        }

        return $processes;
    }

    /**
     * Get the Assets associated with the object
     */
    public function int_assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class)->distinct();
    }

    /**
     * Data subject categories
     */
    public function int_subject_categories(): BelongsToMany
    {
        return $this->belongsToMany(SubjectCategory::class);
    }

    /**
     * Data categories
     */
    public function int_data_categories(): BelongsToMany
    {
        return $this->belongsToMany(DataCategory::class);
    }

    /**
     * Data recipient categories
     */
    public function int_recipient_categories(): BelongsToMany
    {
        return $this->belongsToMany(RecipientCategory::class);
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get confidentiality class
     */
    public function int_confidentialityclass(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityClass::class, 'confidentiality_class_id');
    }

    /**
     * Get integrity class
     */
    public function int_integrityclass(): BelongsTo
    {
        return $this->belongsTo(IntegrityClass::class, 'integrity_class_id');
    }

    /**
     * Get availability class
     */
    public function int_availabilityclass(): BelongsTo
    {
        return $this->belongsTo(AvailabilityClass::class, 'availability_class_id');
    }

    /**
     * Get confidentiality ground
     */
    public function int_confidentiality_ground(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityGround::class, 'confidentiality_ground_id');
    }

    /**
     * Get diary
     */
    public function int_diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class, 'diary_id');
    }

    public function isClassified()
    {
        return ($this->confidentiality_class_id !== null) &&
               ($this->integrity_class_id !== null) &&
               ($this->availability_class_id !== null);
    }

    /**
     * Get risks associated with this object
     */
    public function int_risks(): MorphMany
    {
        return $this->morphMany(Risk::class, 'context');
    }
}
