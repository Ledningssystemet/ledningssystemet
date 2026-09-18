<?php

namespace App\Models;

use App\Models\Concerns\DefersRelationAttributeSync;
use App\Traits\HasNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProcessActivity extends Model
{
    use DefersRelationAttributeSync, HasNotifications;

    public static function getPrettyName($plural = false)
    {
        if ($plural) {
            return __('Tasks');
        } else {
            return __('Task');
        }
    }

    /* Retrieve status for the entire collection of objects */
    public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
    {
        $retval = [];

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
            $model->int_process->touch();
        });

    }

    /**
     * Appended attributes
     */
    protected $appends = [
        'access',
        'responsible_role_name',
        'accountable_role_name',
        'process_activity_suppliers',
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

    public function getResponsibleRoleNameAttribute()
    {
        return $this->int_responsible_role ? $this->int_responsible_role->name : '';
    }

    public function getAccountableRoleNameAttribute()
    {
        return $this->int_accountable_role ? $this->int_accountable_role->name : '';
    }

    public function getProcessActivitySuppliersAttribute()
    {
        $retval = [];

        foreach ($this->int_suppliers as $obj) {
            $retval[] = ['id' => $obj->id, 'name' => $obj->name];
        }

        return $retval;
    }

    public function setProcessActivitySuppliersAttribute($value)
    {
        $this->syncRelationAttribute('processactivitysuppliers', fn ($model) => $model->int_suppliers()->sync($value));
    }

    /**
     * The attributes that shall be visible during serialization.
     */
    protected $visible = [
        'access',
        'id',
        'name',
        'description',
        'ordinal',
        'process_id',
        'responsible_role_id',
        'accountable_role_id',
        'created_at',
        'updated_at',
        'responsible_role_name',
        'accountable_role_name',
        'process_activity_suppliers',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'description',
        'responsible_role_id',
        'accountable_role_id',
        'suppliers',
        'process_activity_suppliers',
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
        $returnCollection = (__CLASS__)::when(request()->has('process_id'), function (Builder $query) {
            $query->where('process_id', request()->input('process_id'));
        })
            ->when(intval(request()->input('id', 0)) > 0, function (Builder $query) {
                $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
            })
            ->orderBy('ordinal');

        if (request()->input('hidechecked', 0) && (new (__CLASS__))->status) {
            return $returnCollection->get()->filter(function ($item) {
                return $item->status['level'] != 'info';
            });
        }

        return $returnCollection->paginate();
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
                'max: 255',
                Rule::unique('process_activities')->ignore($this->id)->where(function ($query) {
                    return $query->where('process_id', '=', $this->process_id);
                }),
            ],
            'responsible_role_id' => 'nullable|exists:App\Models\Role,id',
            'accountable_role_id' => 'nullable|exists:App\Models\Role,id',
        ];
    }

    /**
     * Get the process associated with the processactivity.
     */
    public function int_process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Get the accountable role.
     */
    public function int_accountable_role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'accountable_role_id');
    }

    /**
     * Get the responsible role.
     */
    public function int_responsible_role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id');
    }

    /**
     * Get the Information types associated with the processactivity
     */
    public function int_information_types(): BelongsToMany
    {
        return $this->BelongsToMany(InformationType::class);
    }

    /**
     * Get the suppliers associated with the processactivity.
     */
    public function int_suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class);
    }

    /**
     * Get risks associated with this object
     */
    public function int_risks(): MorphMany
    {
        return $this->morphMany(Risk::class, 'context');
    }
}
