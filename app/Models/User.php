<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'enabled',
        'title',
        'manager_user_id',
        'site_id',
        'departments',
        'roles',
        'accessgroups',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @var array<int, int>|null
     */
    private ?array $pendingDepartmentIds = null;

    /**
     * @var array<int, int>|null
     */
    private ?array $pendingRoleIds = null;

    /**
     * @var array<int, int>|null
     */
    private ?array $pendingAccessGroupIds = null;


    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'site_id' => ['nullable', 'integer', 'min:0', 'exists:sites,id'],
            'departments' => ['nullable', 'array'],
            'roles' => ['nullable', 'array'],
            'accessgroups' => ['nullable', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'email',
                'external_id',
                'title',
            ],
            'relations' => [
                'int_departments' => ['name'],
                'int_manager_user' => ['name'],
            ],
        ];
    }

    public static function crudAppends(): array
    {
        return [
            'departments',
            'roles',
            'accessgroups',
            'direct_reports',
            'activitiescount',
            'assetscount',
            'controlscount',
            'control_actionscount',
            'findingscount',
            'incidentscount',
            'information_typescount',
            'objectivescount',
            'processescount',
            'process_performance_metricscount',
            'riskscount',
            'supplierscount',
            'can_delete',
        ];
    }

    public static function applyCrudIndexFilters(mixed $query, Request $request): void
    {
        $query->withCount([
            'int_activities as activitiescount',
            'int_assets as assetscount',
            'int_controls as controlscount',
            'int_control_actions as control_actionscount',
            'int_findings as findingscount',
            'int_incidents as incidentscount',
            'int_information_types as information_typescount',
            'int_objectives as objectivescount',
            'int_processes as processescount',
            'int_process_performance_metrics as process_performance_metricscount',
            'int_risks_by_riskowner as riskscount',
            'int_suppliers as supplierscount',
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! filled($model->password)) {
                $model->password = Hash::make(Str::random(64));
            }
        });

        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });

        static::saved(function (self $model): void {
            $model->syncRelationsIfNeeded();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Users' : 'User';
    }

    /**
     * Get user claims
     */
    public function userclaims()
    {
        $claimsCacheVersion = $this->updated_at?->getTimestamp() ?? 0;

        return Cache::rememberForever('User.userclaims.'.$this->id.'.'.$claimsCacheVersion, function(){
            $claims = [];

            // Calculate claims
            foreach($this->int_access_groups()->pluck('claims') as $claimarray)
            {
                if((null == $claimarray) ||
                    ("" == $claimarray))
                    continue;

                $claimobj = is_array($claimarray) ? $claimarray : json_decode($claimarray, true);

                if(is_array($claimobj))
                    $claims = array_merge($claims, $claimobj);
            }

            $claims = array_unique($claims);

            // Return
            return $claims;
        });
    }

    /**
     * Does user have all access rights
     */
    public function haveAllAccessRights($claims)
    {
        $userclaims = $this->userclaims();
        if(0 < count(array_intersect(['superadmin.edit'], $userclaims)))
            return true;

        return (count($claims) == count(array_intersect($claims, $this->userclaims())));
    }

    /**
     * Does user have any access rights
     */
    public function haveAnyAccessRights($claims)
    {
        $userclaims = $this->userclaims();
        if(0 < count(array_intersect(['superadmin.edit'], $userclaims)))
            return true;

        return (0 < count(array_intersect($claims, $userclaims)));
    }

    /**
     * Does user have access rights
     */
    public function haveAccessRight($claim)
    {
        return $this->haveAnyAccessRights([$claim]);
    }

    /**
     * Get user communication preferences
     */
    public function getUserCommunicationPreferences()
    {
        // Try to get settings
        $usersettings = DB::table('user_status_email_settings')->where('user_id', $this->id)->first();

        return array(
            'monday' => (null == $usersettings) ? false : $usersettings->monday,
            'tuesday' => (null == $usersettings) ? false : $usersettings->tuesday,
            'wednesday' => (null == $usersettings) ? false : $usersettings->wednesday,
            'thursday' => (null == $usersettings) ? false : $usersettings->thursday,
            'friday' => (null == $usersettings) ? false : $usersettings->friday,
            'saturday' => (null == $usersettings) ? false : $usersettings->saturday,
            'sunday' => (null == $usersettings) ? false : $usersettings->sunday,
        );
    }

    /**
     * Set user communication preferences
     */
    public function setUserCommunicationPreferences($usersettings)
    {
        DB::table('user_status_email_settings')
            ->updateOrInsert(['user_id' => $this->id], $usersettings);

        return $this->getUserCommunicationPreferences();
    }

    /**
     * Issue a new API token
     */
    public function issuetoken(){
        if(request()->user()->cannot('create', \App\Models\PersonalAccessToken::class))
            abort(403);

        return $this->createToken(request()->input('name', date("Y-m-d H:i:s")));

    }

    public function int_manager_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function int_site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function int_access_group_user(): HasMany
    {
        return $this->hasMany(AccessGroupUser::class, 'user_id', 'id');
    }

    public function int_activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'responsible_user_id', 'id');
    }

    public function int_activity_flows(): HasMany
    {
        return $this->hasMany(ActivityFlow::class, 'responsible_user_id', 'id');
    }

    public function int_agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'responsible_user_id', 'id');
    }

    public function int_ai_queries(): HasMany
    {
        return $this->hasMany(AIQuery::class, 'user_id', 'id');
    }

    public function int_assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'responsible_user_id', 'id');
    }

    public function int_control_actions(): HasMany
    {
        return $this->hasMany(ControlAction::class, 'responsible_id', 'id');
    }

    public function int_controls(): HasMany
    {
        return $this->hasMany(Control::class, 'responsible_user_id', 'id');
    }

    public function int_customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'responsible_user_id', 'id');
    }

    public function int_department_user(): HasMany
    {
        return $this->hasMany(DepartmentUser::class, 'user_id', 'id');
    }

    public function int_document_versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'approver_id', 'id');
    }

    public function int_external_provider_group_user(): HasMany
    {
        return $this->hasMany(ExternalProviderGroupUser::class, 'user_id', 'id');
    }

    public function int_findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'created_by', 'id');
    }

    public function int_incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'responsible_user_id', 'id');
    }

    public function int_information_types(): HasMany
    {
        return $this->hasMany(InformationType::class, 'responsible_user_id', 'id');
    }

    public function int_library_documents(): HasMany
    {
        return $this->hasMany(LibraryDocument::class, 'responsible_user_id', 'id');
    }

    public function int_objectives(): HasMany
    {
        return $this->hasMany(Objective::class, 'responsible_user_id', 'id');
    }

    public function int_pending_activities(): HasMany
    {
        return $this->hasMany(PendingActivity::class, 'responsible_user_id', 'id');
    }

    public function int_process_performance_metric_reports(): HasMany
    {
        return $this->hasMany(ProcessPerformanceMetricReport::class, 'reported_by_id', 'id');
    }

    public function int_process_performance_metrics(): HasMany
    {
        return $this->hasMany(ProcessPerformanceMetric::class, 'responsible_user_id', 'id');
    }

    public function int_processes(): HasMany
    {
        return $this->hasMany(Process::class, 'responsible_user_id', 'id');
    }

    public function int_qualification_user(): HasMany
    {
        return $this->hasMany(QualificationUser::class, 'user_id', 'id');
    }

    public function int_requirement_sources(): HasMany
    {
        return $this->hasMany(RequirementSource::class, 'responsible_user_id', 'id');
    }

    public function int_project_user(): HasMany
    {
        return $this->hasMany(ProjectUser::class, 'user_id', 'id');
    }

    public function int_projects(): HasMany
    {
        return $this->hasMany(Project::class, 'responsible_user_id', 'id');
    }

    public function int_risks_by_created_by(): HasMany
    {
        return $this->hasMany(Risk::class, 'created_by', 'id');
    }

    public function int_risks_by_riskowner(): HasMany
    {
        return $this->hasMany(Risk::class, 'riskowner_id', 'id');
    }

    public function int_role_user(): HasMany
    {
        return $this->hasMany(RoleUser::class, 'user_id', 'id');
    }

    public function int_sites(): HasMany
    {
        return $this->hasMany(Site::class, 'responsible_user_id', 'id');
    }

    public function int_suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'responsible_user_id', 'id');
    }

    public function int_user_competence(): HasMany
    {
        return $this->hasMany(UserCompetence::class, 'user_id', 'id');
    }

    public function int_user_notification_channels(): HasMany
    {
        return $this->hasMany(UserNotificationChannel::class, 'user_id', 'id');
    }

    public function int_user_notification_queue_entries(): HasMany
    {
        return $this->hasMany(UserNotificationQueueEntry::class, 'user_id', 'id');
    }

    public function int_user_status_email_settings(): HasMany
    {
        return $this->hasMany(UserStatusEmailSetting::class, 'user_id', 'id');
    }

    public function int_users(): HasMany
    {
        return $this->hasMany(User::class, 'manager_user_id', 'id');
    }

    public function int_access_groups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class, 'access_group_user', 'user_id', 'access_group_id')
            ->withTimestamps();
    }

    public function int_departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user', 'user_id', 'department_id')
            ->withTimestamps();
    }

    public function int_external_provider_groups(): BelongsToMany
    {
        return $this->belongsToMany(ExternalProviderGroup::class, 'external_provider_group_user', 'user_id', 'external_provider_group_id')
            ->withTimestamps();
    }

    public function int_projects_2(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user', 'user_id', 'project_id')
            ->withTimestamps();
    }

    public function int_roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * @return array<int, int>
     */
    public function getDepartmentsAttribute(): array
    {
        $departments = $this->relationLoaded('int_departments')
            ? $this->int_departments
            : $this->int_departments()->select('departments.id')->get();

        return $departments
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setDepartmentsAttribute(mixed $value): void
    {
        $this->pendingDepartmentIds = $this->normalizeRelatedIds($value);
    }

    /**
     * @return array<int, int>
     */
    public function getRolesAttribute(): array
    {
        $roles = $this->relationLoaded('int_roles')
            ? $this->int_roles
            : $this->int_roles()->select('roles.id')->get();

        return $roles
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setRolesAttribute(mixed $value): void
    {
        $this->pendingRoleIds = $this->normalizeRelatedIds($value);
    }

    /**
     * @return array<int, int>
     */
    public function getAccessgroupsAttribute(): array
    {
        $accessGroups = $this->relationLoaded('int_access_groups')
            ? $this->int_access_groups
            : $this->int_access_groups()->select('access_groups.id')->get();

        return $accessGroups
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setAccessgroupsAttribute(mixed $value): void
    {
        $this->pendingAccessGroupIds = $this->normalizeRelatedIds($value);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getDirectReportsAttribute(): array
    {
        $directReports = $this->relationLoaded('int_users')
            ? $this->int_users
            : $this->int_users()->select('users.id', 'users.name')->orderBy('name')->get();

        return $directReports
            ->map(static fn (self $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ])
            ->values()
            ->all();
    }

    public function getActivitiescountAttribute(): int
    {
        return $this->resolveCountAttribute('activitiescount', 'int_activities');
    }

    public function getAssetscountAttribute(): int
    {
        return $this->resolveCountAttribute('assetscount', 'int_assets');
    }

    public function getControlscountAttribute(): int
    {
        return $this->resolveCountAttribute('controlscount', 'int_controls');
    }

    public function getControlActionscountAttribute(): int
    {
        return $this->resolveCountAttribute('control_actionscount', 'int_control_actions');
    }

    public function getFindingscountAttribute(): int
    {
        return $this->resolveCountAttribute('findingscount', 'int_findings');
    }

    public function getIncidentscountAttribute(): int
    {
        return $this->resolveCountAttribute('incidentscount', 'int_incidents');
    }

    public function getInformationTypescountAttribute(): int
    {
        return $this->resolveCountAttribute('information_typescount', 'int_information_types');
    }

    public function getObjectivescountAttribute(): int
    {
        return $this->resolveCountAttribute('objectivescount', 'int_objectives');
    }

    public function getProcessescountAttribute(): int
    {
        return $this->resolveCountAttribute('processescount', 'int_processes');
    }

    public function getProcessPerformanceMetricscountAttribute(): int
    {
        return $this->resolveCountAttribute('process_performance_metricscount', 'int_process_performance_metrics');
    }

    public function getRiskscountAttribute(): int
    {
        return $this->resolveCountAttribute('riskscount', 'int_risks_by_riskowner');
    }

    public function getSupplierscountAttribute(): int
    {
        return $this->resolveCountAttribute('supplierscount', 'int_suppliers');
    }

    public function getCanDeleteAttribute(): bool
    {
        if (filled($this->external_id)) {
            return false;
        }

        return $this->activitiescount === 0
            && $this->assetscount === 0
            && $this->controlscount === 0
            && $this->control_actionscount === 0
            && $this->findingscount === 0
            && $this->incidentscount === 0
            && $this->information_typescount === 0
            && $this->objectivescount === 0
            && $this->processescount === 0
            && $this->process_performance_metricscount === 0
            && $this->riskscount === 0
            && $this->supplierscount === 0;
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

    /**
     * @return array<int, int>|null
     */
    private function normalizeRelatedIds(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value)) {
            return null;
        }

        return collect($value)
            ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCountAttribute(string $attribute, string $relationMethod): int
    {
        if (array_key_exists($attribute, $this->attributes)) {
            return (int) $this->attributes[$attribute];
        }

        return $this->{$relationMethod}()->count();
    }

    private function syncRelationsIfNeeded(): void
    {
        if ($this->pendingDepartmentIds !== null) {
            $this->int_departments()->sync($this->pendingDepartmentIds);
            $this->pendingDepartmentIds = null;
        }

        if ($this->pendingRoleIds !== null) {
            $this->int_roles()->sync($this->pendingRoleIds);
            $this->pendingRoleIds = null;
        }

        if ($this->pendingAccessGroupIds !== null) {
            $this->int_access_groups()->sync($this->pendingAccessGroupIds);
            $this->pendingAccessGroupIds = null;
        }
    }
}
