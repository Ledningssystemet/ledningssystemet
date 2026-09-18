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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class Customer extends Model
{
    use DefersRelationAttributeSync, HasCustomProperties, HasMessages, HasNotifications, HasTags;

    public static function getPrettyName($plural = false)
    {
        if ($plural) {
            return __('Customers');
        } else {
            return __('Customer');
        }
    }

    /* Retrieve status for the entire collection of objects */
    public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
    {
        if (($user != null) && $user->cannot('update', Customer::class)) {
            return [];
        }

        $retval = [];
        $url = ((($user != null) && $user->can('index', get_called_class())) ||
           (($user == null) && (auth()->user() != null) && auth()->user()->can('index', get_called_class())))
           ? url()->query('/inventory/customers')
           : null;

        $countWithoutAssignment = Customer::whereNull('responsible_user_id')->count();
        if (! $personalOnly && $countWithoutAssignment) {
            $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => Customer::getPrettyName($countWithoutAssignment > 1).' '.__('without assignment'), 'url' => $url];
        }

        if ($department == null) {
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

    }

    /**
     * Appended attributes
     */
    protected $appends = [
        'access',
        'processes',
        'riskcount',
        'tags',
        'messagecount',
        'status',
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

    public function getProcessesAttribute()
    {
        if ($this->relationLoaded('int_processes')) {
            return $this->getRelation('int_processes')
                ->map(fn ($obj) => ['id' => $obj->id, 'name' => $obj->name])
                ->all();
        }

        $retval = [];
        foreach ($this->int_processes as $obj) {
            $retval[] = ['id' => $obj->id, 'name' => $obj->name];
        }

        return $retval;
    }

    public function setProcessesAttribute($value)
    {
        $this->syncRelationAttribute('processes', fn ($model) => $model->int_processes()->sync(($value != null) ? $value : []));
    }

    public function getRiskcountAttribute()
    {
        if (array_key_exists('int_risks_count', $this->attributes)) {
            return intval($this->attributes['int_risks_count']);
        }

        return $this->int_risks()->count();
    }

    public function getFilesAttribute()
    {
        return DB::table('files')->where('object_type', $this::class)->where('object_id', $this->id)->select(['id', 'filename', 'name', 'description', 'contenttype', 'contentlength'])->get();
    }

    public function getAgreementsAttribute()
    {
        return $this->int_agreements;
    }

    /**
     * The attributes that shall be visible during serialization.
     */
    protected $visible = [
        'access',
        'id',
        'name',
        'legal_reg',
        'ext_id',
        'description',
        'responsible_user_id',
        'created_at',
        'updated_at',
        'dpo_email',
        'dpo_name',
        'processes',
        'riskcount',
        'tags',
        'messagecount',
        'status',
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
        'legal_reg',
        'ext_id',
        'description',
        'responsible_user_id',
        'dpo_email',
        'dpo_name',
        'processes',
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
                        ->orWhere('legal_reg', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('tags', function (Builder $tagQuery) use ($search) {
                            $tagQuery->where('name', 'LIKE', '%'.$search.'%');
                        });
                });
            })
            ->when(intval(request()->input('showmyonly', 0)) == 1, function (Builder $query) {
                $query->where('responsible_user_id', auth()->user()->id);
            })
            ->when(intval(request()->input('responsible_user_id', 0)) > 0, function (Builder $query) {
                $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
            })
            ->when(intval(request()->input('tag_id', 0)) > 0, function (Builder $query) {
                $query->whereHas('tags', function (Builder $q) {
                    $q->where('tags.id', intval(request()->input('tag_id', 0)));
                });
            })
            ->when(intval(request()->input('id', 0)) > 0, function (Builder $query) use ($table) {
                $query->where($table.'.id', intval(request()->input('id', 0)));
            })
            ->when(count(array_filter(array_keys(request()->all()), fn ($var) => str_starts_with($var, 'customproperty_'))) > 0, function (Builder $query) {
                self::getIndexQuery($query);
            })
            ->with([
                'tags',
                'int_processes:id,name',
                'int_agreements',
            ])
            ->withCount([
                'messages',
                'int_risks',
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
            'name' => ['required', 'max:255', Rule::unique('customers')->ignore($this->id)],
            'legal_reg' => 'nullable|max:255',
            'ext_id' => 'nullable|max:255',
            'responsible_user_id' => 'nullable|exists:App\Models\User,id',
            'dpo_name' => 'nullable|max:255',
            'dpo_email' => 'nullable|max:255|email',
        ];
    }

    /**
     * Get the Assets associated with the object
     */
    public function int_processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class);
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Get risks associated with this object
     */
    public function int_risks(): MorphMany
    {
        return $this->morphMany(Risk::class, 'context');
    }

    public function int_agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'customer_id');
    }
}
