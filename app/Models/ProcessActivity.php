<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class ProcessActivity extends Model
{
    use HasFactory;

    protected $table = 'process_activities';

    protected $fillable = ['uid', 'name', 'description', 'bpmnId', 'ordinal', 'process_id', 'responsible_role_id', 'accountable_role_id'];

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
            'uid' => ['nullable', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'bpmnId' => ['required', 'string', 'max:255'],
            'ordinal' => ['required', 'integer'],
            'process_id' => ['required', 'integer', 'min:0', 'exists:processes,id'],
            'responsible_role_id' => ['nullable', 'integer', 'min:0', 'exists:roles,id'],
            'accountable_role_id' => ['nullable', 'integer', 'min:0', 'exists:roles,id'],
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
        return $plural ? 'Process Activities' : 'Process Activity';
    }

    public function int_accountable_role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'accountable_role_id');
    }

    public function int_process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function int_responsible_role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id');
    }

    public function int_information_type_process_activity(): HasMany
    {
        return $this->hasMany(InformationTypeProcessActivity::class, 'process_activity_id', 'id');
    }

    public function int_process_activity_supplier(): HasMany
    {
        return $this->hasMany(ProcessActivitySupplier::class, 'process_activity_id', 'id');
    }

    public function int_information_types(): BelongsToMany
    {
        return $this->belongsToMany(InformationType::class, 'information_type_process_activity', 'process_activity_id', 'information_type_id');
    }

    public function int_suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'process_activity_supplier', 'process_activity_id', 'supplier_id');
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
