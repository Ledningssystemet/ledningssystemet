


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
class Department extends Model
{
    use HasFactory;
    protected $table = 'departments';
    protected $fillable = ['name', 'external_provider_group_id', 'parent_department_id', 'site_id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'external_provider_group_id' => ['nullable', 'integer', 'min:0', 'exists:external_provider_groups,id'],
            'parent_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'site_id' => ['nullable', 'integer', 'min:0', 'exists:sites,id'],
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
        return $plural ? 'Departments' : 'Department';
    }

    public function int_external_provider_group(): BelongsTo
    {
        return $this->belongsTo(ExternalProviderGroup::class, 'external_provider_group_id');
    }

    public function int_parent_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function int_site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function int_compliance_evaluation_requirement_findings(): HasMany
    {
        return $this->hasMany(ComplianceEvaluationRequirementFinding::class, 'department_id', 'id');
    }

    public function int_department_user(): HasMany
    {
        return $this->hasMany(DepartmentUser::class, 'department_id', 'id');
    }

    public function int_departments(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id', 'id');
    }

    public function int_findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'department_id', 'id');
    }

    public function int_objectives(): HasMany
    {
        return $this->hasMany(Objective::class, 'department_id', 'id');
    }
    
    
    
    
    
    
    

    public function int_processes(): HasMany
    {
        return $this->hasMany(Process::class, 'department_id', 'id');
    }

    public function int_risk_projects(): HasMany
    {
        return $this->hasMany(RiskProject::class, 'department_id', 'id');
    }

    public function int_risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'department_id', 'id');
    }

    public function int_users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user', 'department_id', 'user_id')
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
