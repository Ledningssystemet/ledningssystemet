


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
class Supplier extends Model
{
    use HasFactory;
    protected $table = 'suppliers';
    protected $fillable = ['name', 'description', 'responsible_user_id', 'processoragreementdescription', 'dataprocessor', 'external_supplier_id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'dataprocessor' => 'boolean',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'processoragreementdescription' => ['nullable', 'string'],
            'dataprocessor' => ['nullable', 'boolean'],
            'external_supplier_id' => ['nullable', 'string', 'max:255'],
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
        return $plural ? 'Suppliers' : 'Supplier';
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_agreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'supplier_id', 'id');
    }

    public function int_assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'supplier_id', 'id');
    }

    public function int_process_activity_supplier(): HasMany
    {
        return $this->hasMany(ProcessActivitySupplier::class, 'supplier_id', 'id');
    }

    public function int_supplier_documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class, 'supplier_id', 'id');
    }

    public function int_supplier_supplier_category(): HasMany
    {
        return $this->hasMany(SupplierSupplierCategory::class, 'supplier_id', 'id');
    }

    public function int_supplier_supplier_requirement(): HasMany
    {
        return $this->hasMany(SupplierSupplierRequirement::class, 'supplier_id', 'id');
    }

    public function int_process_activities(): BelongsToMany
    {
        return $this->belongsToMany(ProcessActivity::class, 'process_activity_supplier', 'supplier_id', 'process_activity_id');
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
