<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
use App\Traits\HasMessages;
use App\Traits\HasNotifications;
use App\Traits\HasTags;
use App\Models\Concerns\DefersRelationAttributeSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class Site extends Model
{
    use DefersRelationAttributeSync, HasCustomProperties, HasMessages, HasNotifications, HasTags;

    public static function getPrettyName($plural = false)
    {
        if ($plural) {
            return __('Sites');
        } else {
            return __('Site');
        }
    }

    /* Retrieve status for the entire collection of objects */
    public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
    {
        if (($user != null) && $user->cannot('update', Site::class)) {
            return [];
        }

        $retval = [];
        $url = ((($user != null) && $user->can('index', get_called_class())) ||
           (($user == null) && (auth()->user() != null) && auth()->user()->can('index', get_called_class())))
           ? url()->query('/systemadmin/sites')
           : null;

        $countWithoutAssignment = Site::whereNull('responsible_user_id')->count();
        if (! $personalOnly && $countWithoutAssignment) {
            $retval[] = ['level' => 'danger', 'count' => $countWithoutAssignment, 'text' => Site::getPrettyName($countWithoutAssignment > 1).' '.__('without assignment'), 'url' => $url];
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
        'assets',
        'users',
        'departments',
        'riskcount',
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
        if (! $this->responsible_user_id) {
            return ['icon' => 'warning', 'level' => 'danger', 'text' => __('A responsible user has not been assigned')];
        }

        return ['icon' => 'check', 'level' => 'info', 'text' => ''];
    }

    public function getTagsAttribute()
    {
        return $this->tags()->get();
    }

    public function getMessagecountAttribute()
    {
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

    public function setAssetsAttribute($value)
    {
        $objs = ($value != null) ? $value : [];

        $this->syncRelationAttribute('assets', function ($model) use ($objs) {
            DB::table('assets')->where('site_id', $model->id)->whereNotIn('id', $objs)->update(['site_id' => null]);

            if (count($objs) > 0) {
                DB::table('assets')->whereIn('id', $objs)->update(['site_id' => $model->id]);
            }
        });
    }

    public function getUsersAttribute()
    {
        $retval = [];
        foreach ($this->int_users as $obj) {
            $retval[] = ['id' => $obj->id, 'name' => $obj->name];
        }

        return $retval;
    }

    public function setUsersAttribute($value)
    {
        $objs = ($value != null) ? $value : [];

        $this->syncRelationAttribute('users', function ($model) use ($objs) {
            DB::table('users')->where('site_id', $model->id)->whereNotIn('id', $objs)->update(['site_id' => null]);

            if (count($objs) > 0) {
                DB::table('users')->whereIn('id', $objs)->update(['site_id' => $model->id]);
            }
        });
    }

    public function getDepartmentsAttribute()
    {
        $retval = [];
        foreach ($this->int_departments as $obj) {
            $retval[] = ['id' => $obj->id, 'name' => $obj->name];
        }

        return $retval;
    }

    public function setDepartmentsAttribute($value)
    {
        $objs = ($value != null) ? $value : [];

        $this->syncRelationAttribute('departments', function ($model) use ($objs) {
            DB::table('departments')->where('site_id', $model->id)->whereNotIn('id', $objs)->update(['site_id' => null]);

            if (count($objs) > 0) {
                DB::table('departments')->whereIn('id', $objs)->update(['site_id' => $model->id]);
            }
        });
    }

    public function getRiskcountAttribute()
    {
        return $this->int_risks()->count();
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
        'external_provider_group_id',
        'assets',
        'users',
        'departments',
        'riskcount',
        'tags',
        'messagecount',
        'status',
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
        'external_provider_group_id',
        'users',
        'departments',
        'assets',
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
        $returnCollection = (__CLASS__)::where(function (Builder $query) {
            $query->when((request()->has('search') && (trim(request()->input('search')) != '')), function (Builder $query) {
                $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                    ->orWhere('description', 'LIKE', '%'.request()->input('search').'%')
                    ->orWhereHas('tags', function (Builder $query) {
                        $query->where('name', 'LIKE', '%'.request()->input('search').'%');
                    });
            });
        })
            ->when((intval(request()->input('showmyonly', 0)) == 1), function (Builder $query) {
                $query->where('responsible_user_id', auth()->user()->id);
            })
            ->when(intval(request()->input('responsible_user_id', 0)) > 0, function (Builder $query) {
                $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
            })
            ->when((intval(request()->input('tag_id', 0)) > 0), function (Builder $query) {
                $query->whereHas('tags', function (Builder $query) {
                    $query->where('tags.id', intval(request()->input('tag_id', 0)));
                });
            })
            ->when(intval(request()->input('id', 0)) > 0, function (Builder $query) {
                $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
            })
            ->when(count(array_filter(array_keys(request()->all()), function ($var) {
                return strpos($var, 'customproperty_') === 0;
            })) > 0, function (Builder $query) {
                (__CLASS__)::getIndexQuery($query);
            })
            ->orderBy('name');

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
                'max:255',
                Rule::unique('sites')->ignore($this->id),
            ],
            'responsible_user_id' => 'nullable|exists:App\Models\User,id',
            'external_provider_group_id' => 'nullable|exists:external_provider_groups,id',
        ];
    }

    /**
     * Get the Assets associated with the object
     */
    public function int_assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get the Users associated with the object
     */
    public function int_users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the Departments associated with the object
     */
    public function int_departments(): HasMany
    {
        return $this->hasMany(Department::class);
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
}
