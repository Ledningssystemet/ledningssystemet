<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class Partner extends Model
{
    use HasFactory;

    protected $table = 'partners';

    protected $fillable = ['uid', 'name', 'description', 'url', 'authtoken', 'lastseen', 'company_risk_department_id', 'department_risk_department_id', 'process_risk_department_id', 'information_type_risk_department_id', 'asset_risk_department_id', 'supplier_risk_department_id', 'fallback_risk_department_id', 'customer_risk_department_id', 'site_risk_department_id'];

    protected $hidden = ['authtoken'];

    protected function casts(): array
    {
        return [
            'lastseen' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'uid' => ['nullable', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'url' => ['required', 'string', 'max:255'],
            'authtoken' => ['nullable', 'string', 'max:64'],
            'lastseen' => ['nullable', 'date'],
            'company_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'department_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'process_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'information_type_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'asset_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'supplier_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'fallback_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'customer_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:customers,id'],
            'site_risk_department_id' => ['nullable', 'integer', 'min:0', 'exists:sites,id'],
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
        return $plural ? 'Partners' : 'Partner';
    }

    public function int_asset_risk_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'asset_risk_department_id');
    }

    public function int_company_risk_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'company_risk_department_id');
    }

    public function int_site_risk_department(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_risk_department_id');
    }

    public function int_customer_risk_department(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_risk_department_id');
    }

    public function int_department_risk_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_risk_department_id');
    }

    public function int_fallback_risk_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'fallback_risk_department_id');
    }

    public function int_information_type_risk_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'information_type_risk_department_id');
    }

    public function int_process_risk_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'process_risk_department_id');
    }

    public function int_supplier_risk_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'supplier_risk_department_id');
    }

    public function int_activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'partner_id', 'id');
    }

    public function int_activity_flow_templates(): HasMany
    {
        return $this->hasMany(ActivityFlowTemplate::class, 'partner_id', 'id');
    }

    public function int_controls(): HasMany
    {
        return $this->hasMany(Control::class, 'partner_id', 'id');
    }

    public function int_library_documents(): HasMany
    {
        return $this->hasMany(LibraryDocument::class, 'partner_id', 'id');
    }

    public function int_property_tabs(): HasMany
    {
        return $this->hasMany(PropertyTab::class, 'partner_id', 'id');
    }

    public function int_requirement_sources(): HasMany
    {
        return $this->hasMany(RequirementSource::class, 'partner_id', 'id');
    }

    public function int_risk_project_types(): HasMany
    {
        return $this->hasMany(RiskProjectType::class, 'partner_id', 'id');
    }

    public function int_risk_template_evaluation_attempts(): HasMany
    {
        return $this->hasMany(RiskTemplateEvaluationAttempt::class, 'partner_id', 'id');
    }

    public function int_risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'partner_id', 'id');
    }

    public function int_supplier_categories(): HasMany
    {
        return $this->hasMany(SupplierCategory::class, 'partner_id', 'id');
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
