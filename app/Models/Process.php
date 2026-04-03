<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class Process extends Model
{
    use HasFactory;

    protected $table = 'processes';

    protected $fillable = ['uid', 'name', 'description', 'bpmn', 'publishedbpmn', 'svg', 'department_id', 'responsible_user_id', 'isstartprocess', 'legalbasisdescription', 'thirdcountrytransferdescription', 'thirdcountrytransferprotectiondescription', 'securitymeasuredescription', 'dataprocessor', 'data_processor_processing_activities'];

    protected function casts(): array
    {
        return [
            'isstartprocess' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'dataprocessor' => 'boolean',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'uid' => ['nullable', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'bpmn' => ['nullable', 'string'],
            'publishedbpmn' => ['nullable', 'string'],
            'svg' => ['nullable', 'string'],
            'department_id' => ['required', 'integer', 'min:0', 'exists:departments,id'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'isstartprocess' => ['required', 'boolean'],
            'legalbasisdescription' => ['nullable', 'string'],
            'thirdcountrytransferdescription' => ['nullable', 'string'],
            'thirdcountrytransferprotectiondescription' => ['nullable', 'string'],
            'securitymeasuredescription' => ['nullable', 'string'],
            'dataprocessor' => ['required', 'boolean'],
            'data_processor_processing_activities' => ['nullable', 'string'],
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
        return $plural ? 'Processes' : 'Process';
    }

    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_asset_information_type(): HasMany
    {
        return $this->hasMany(AssetInformationType::class, 'process_id', 'id');
    }

    public function int_customer_process(): HasMany
    {
        return $this->hasMany(CustomerProcess::class, 'process_id', 'id');
    }

    public function int_legal_basis_process(): HasMany
    {
        return $this->hasMany(LegalBasisProcess::class, 'process_id', 'id');
    }

    public function int_library_document_processes(): HasMany
    {
        return $this->hasMany(LibraryDocumentProcess::class, 'process_id', 'id');
    }

    public function int_process_activities(): HasMany
    {
        return $this->hasMany(ProcessActivity::class, 'process_id', 'id');
    }

    public function int_process_hrefs(): HasMany
    {
        return $this->hasMany(ProcessHref::class, 'process_id', 'id');
    }

    public function int_process_links_by_linked_process(): HasMany
    {
        return $this->hasMany(ProcessLink::class, 'linked_process_id', 'id');
    }

    public function int_process_links_by_process(): HasMany
    {
        return $this->hasMany(ProcessLink::class, 'process_id', 'id');
    }

    public function int_process_process_performance_metric(): HasMany
    {
        return $this->hasMany(ProcessProcessPerformanceMetric::class, 'process_id', 'id');
    }

    public function int_process_sustainability_aspects(): HasMany
    {
        return $this->hasMany(ProcessSustainabilityAspect::class, 'process_id', 'id');
    }

    public function int_customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_process', 'process_id', 'customer_id');
    }

    public function int_legal_bases(): BelongsToMany
    {
        return $this->belongsToMany(LegalBasis::class, 'legal_basis_process', 'process_id', 'legal_basis_id');
    }

    public function int_library_documents(): BelongsToMany
    {
        return $this->belongsToMany(LibraryDocument::class, 'library_document_processes', 'process_id', 'library_document_id')
            ->withTimestamps();
    }

    public function int_processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'process_links', 'linked_process_id', 'process_id');
    }

    public function int_process_performance_metrics(): BelongsToMany
    {
        return $this->belongsToMany(ProcessPerformanceMetric::class, 'process_process_performance_metric', 'process_id', 'process_performance_metric_id')
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
