


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;
class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'email_verified_at', 'password', 'enabled', 'remember_token', 'external_id', 'title', 'manager_user_id', 'site_id', 'last_login_at'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
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
            'email' => ['required', 'string', 'max:255'],
            'email_verified_at' => ['nullable', 'date'],
            'password' => ['required', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'remember_token' => ['nullable', 'string', 'max:100'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'site_id' => ['nullable', 'integer', 'min:0', 'exists:sites,id'],
            'last_login_at' => ['nullable', 'date'],
        ];
    }

    protected static function booted(): void
    {

        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Users' : 'User';
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

    public function int_risk_project_user(): HasMany
    {
        return $this->hasMany(RiskProjectUser::class, 'user_id', 'id');
    }

    public function int_risk_projects(): HasMany
    {
        return $this->hasMany(RiskProject::class, 'responsible_user_id', 'id');
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

    public function int_risk_projects_2(): BelongsToMany
    {
        return $this->belongsToMany(RiskProject::class, 'risk_project_user', 'user_id', 'risk_project_id')
            ->withTimestamps();
    }

    public function int_roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps();
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

    public function int_ignored_risks_as_context(): MorphMany
    {
        return $this->morphMany(IgnoredRisk::class, 'context', 'context_type', 'context_id');
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

    public function int_risk_template_evaluation_attempts_as_context(): MorphMany
    {
        return $this->morphMany(RiskTemplateEvaluationAttempt::class, 'context', 'context_type', 'context_id');
    }

    public function int_risks_as_context(): MorphMany
    {
        return $this->morphMany(Risk::class, 'context', 'context_type', 'context_id');
    }

    public function int_vector_embeddings_as_embeddable(): MorphMany
    {
        return $this->morphMany(VectorEmbedding::class, 'embeddable', 'embeddable_type', 'embeddable_id');
    }
}
