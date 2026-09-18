<?php

namespace App\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Contracts\Permission as SpatiePermissionContract;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission as SpatiePermission;
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

      // Keep Spatie role in sync when claims are saved
      static::saved(function (AccessGroup $model) {
         $claims = is_array($model->claims) ? $model->claims : [];
         $model->permissions()->sync(
            SpatiePermission::query()
               ->whereIn('name', $claims)
               ->where('guard_name', self::SHARED_GUARD)
               ->pluck('id')
               ->all()
         );
         app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
      });

      // Flush Spatie permission cache when access group is deleted
      static::deleted(function (AccessGroup $model) {
         app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
      });
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'users',
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

   /**
    * Claims accessor/mutator
    */
   protected function claims(): Attribute
   {
      return Attribute::make(
         get: fn($value) => json_decode($value, true),
         set: fn($value) => json_encode($value),
      );
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'claims',
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
      'claims',
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
         'claims' => 'nullable',
         'external_provider_group_id' => 'nullable|exists:external_provider_groups,id',
         'users' => 'nullable',
      ];
   }

   /**
    * Get the Users associated with the object
    */
   public function int_users(): BelongsToMany
   {
      return $this->belongsToMany(User::class);
   }

   public function int_risk_level(): BelongsTo
   {
      return $this->belongsTo(Risk_level::class, 'risk_level_id');
   }

   /**
    * Get the available claims
    */
   public static function allClaims()
   {
      $accesslevels = [];

      /* Groups that are used directly in code (i.e. not only in the AppServiceProvider model authorization) */

      // Always available
      $accesslevels = array_merge($accesslevels, array(
         'dashboard.read' => __("Dashboard"), // Show dashboard
         'managementtools.edit' => __("Management tools"), // Management tools (e.g. activity flows etc)
         'allcontrolactions.read' => __("Show all control actions"),
	 	 'showobjectivestatus.read' => __("Show current objective status"), // Can users get information on objective status?
      ));

      // Only if staff is enabled
      if (!config('ledningssystemet.disable_staff')) {
         $accesslevels = array_merge($accesslevels, array(
            'employeemanagement.edit' => __("Employee management"),
            'subordinateemployeemenagement.edit' => __("Subordinate only employee management, full access"),
         ));
      }

      /* Groups only used in AppServiceProvider model authorization */
      $accesslevels = array_merge($accesslevels, array(
         'superadmin.edit' => __("Superuser, no access limitations"),
         'agreements.read' => __("Agreements, read only"),
         'agreements.edit' => __("Agreements, full access"),
         'customers.read' => __("Customers, read only"),
         'customers.edit' => __("Customers, full access"),
         'forms.edit' => __("Forms, full access"),
         'ghg.edit' => __("GHG, full access"),
         'ghg.read' => __("GHG, read only"),
         'processes.read' => __("Processes, informationtypes and assets, read only"),
         'processes.edit' => __("Processes, informationtypes and assets, full access"),
         'requirements.read' => __("Requirements, read only"),
         'requirements.edit' => __("Requirements, full access"),
         'complianceevaluations.read' => __("Compliance evaluations, read only"),
         'complianceevaluations.edit' => __("Compliance evaluations, full access"),
         'controls.read' => __("Controls, read only"),
         'controls.edit' => __("Controls, full access"),
         'systemadministrator.edit' => __("System administrator"),
         'riskdepartment.edit' => __("Risk management, my departments"),
         'riskall.edit' => __("Risk management, all risks"),
         'riskadministrator.edit' => __("Risk administrator"),
         'chemicalregister.read' => __("Chemical register, read only"),
         'chemicalregister.edit' => __("Chemical register, full access"),
         'incidents.read' => __("Incidents, read only"),
         'incidents.edit' => __("Incidents, full access"),
         'documentpublisher.edit' => __("Document publisher"),
      ));

      // Only if AI is configured
      if (config('ledningssystemet.openai_endpoint'))
      {
         $accesslevels = array_merge($accesslevels, array(
            'ai.edit' => __("Use AI features"),
         ));
      }

      if (!config('ledningssystemet.disable_finding')) {
         $accesslevels = array_merge($accesslevels, array(
            'findings.read' => __("Findings, read only"),
            'findings.edit' => __("Findings, full access"),
         ));
      }

      if (!config('ledningssystemet.disable_archival')) {
         $accesslevels = array_merge($accesslevels, array(
            'docmgmtplan.read' => __("Document management plan, read"),
         ));
      }

      if (!config('ledningssystemet.disable_gdpr')) {
         $accesslevels = array_merge($accesslevels, array(
            'processingregister.read' => __("Data processing register, read only"),
            'processingregister.edit' => __("Data processing register, full access"),
         ));
      }

      if (!config('ledningssystemet.disable_supplier')) {
         $accesslevels = array_merge($accesslevels, array(
            'suppliers.read' => __("Suppliers, read only"),
            'suppliers.edit' => __("Suppliers, full access"),
         ));
      }

	 $accesslevels = array_merge($accesslevels, array(
		'processmetrics.read' => __("Process metrics, read only"),
		'processmetrics.edit' => __("Process metrics, full access"),
		'objectives.read' => __("Objectives, read only"),
		'objectives.edit' => __("Objectives, full access"),
		'sustainabilityaspects.read' => __("Sustainability aspects, read only"),
		'sustainabilityaspects.edit' => __("Sustainability aspects, full access"),
	 ));

      return $accesslevels;
   }


}
