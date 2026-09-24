<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\Concerns\DefersRelationAttributeSync;


class AccessGroup extends SpatieRole
{
   use DefersRelationAttributeSync;

   public const SHARED_GUARD = 'web';

   public static function getPrettyName($plural = false)
   {
      if ($plural) {
         return __("Access group");
      } else {
         return __("Access group");
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

      static::saving(function (AccessGroup $model) {
         Validator::make($model->toArray(), $model->getValidationRules())->validate();

         $model->guard_name = self::SHARED_GUARD;
      });
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'users',
      'permission_ids',
      'status',
   ];

   public function getAccessAttribute($user = null)
   {
      if (null == $user)
         $user = auth()->user();

      if (null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }


   public function getStatusAttribute()
   {
      if ($this->external_provider_group_id)
         return ['icon' => 'cloud', 'level' => 'info', 'text' => ''];

      return ['icon' => '', 'level' => '', 'text' => ''];
   }

   public function getUsersAttribute()
   {
      $retval = [];
      foreach ($this->int_users as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }

   public function setUsersAttribute($value)
   {
      $this->syncRelationAttribute('users', fn ($model) => $model->int_users()->sync($value));
   }

   public function getPermissionIdsAttribute(): array
   {
      return $this->permissions()->pluck('permissions.id')->all();
   }

   public function setPermissionIdsAttribute($value): void
   {
      $this->syncRelationAttribute(
         'permission_ids',
         fn($model) => $model->syncPermissions(array_map('intval', $value ?? []))
      );
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'permission_ids',
      'risk_level_id',
      'created_at',
      'updated_at',
      'external_provider_group_id',
      'users',
      'status',
   ];


   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'guard_name',
      'risk_level_id',
      'permission_ids',
      'external_provider_group_id',
      'users',
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
      $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
         $query->where('name', 'LIKE', '%' . request()->input('search') . '%');
      })
         ->when((1 == intval(request()->input('showmyonly', 0))), function (Builder $query) {
            $query->where('responsible_user_id', auth()->user()->id);
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable() . '.id', intval(request()->input('id', 0)));
         })
         ->orderBy('name');

      if (request()->input('hidechecked', 0) && (new (__CLASS__))->status) {
         return $returnCollection->get()->filter(function ($item) {
            return ($item->status['level'] != 'info');
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
         'name' => ['required', Rule::unique('access_groups')->ignore($this->id)],
         'risk_level_id' => 'nullable|exists:App\Models\RiskLevel,id',
         'permission_ids' => 'nullable',
         'external_provider_group_id' => 'nullable|exists:external_provider_groups,id',
         'users' => 'nullable',
      ];
   }

   /**
    * Get the Users associated with the object
    */
   public function int_users(): MorphToMany
   {
      return $this->morphedByMany(User::class, 'model', 'access_group_user', 'access_group_id', 'model_id');
   }

   public function int_risk_level(): BelongsTo
   {
      return $this->belongsTo(Risk_level::class, 'risk_level_id');
   }

}
