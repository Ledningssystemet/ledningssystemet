


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
class Agreement extends Model
{
    use HasFactory;
    protected $table = 'agreements';
    protected $fillable = ['name', 'description', 'startdate', 'enddate', 'reminderdate', 'responsible_user_id', 'supplier_id', 'customer_id', 'archived_at'];

    protected function casts(): array
    {
        return [
            'startdate' => 'date',
            'enddate' => 'date',
            'reminderdate' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startdate' => ['nullable', 'date'],
            'enddate' => ['nullable', 'date'],
            'reminderdate' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'supplier_id' => ['nullable', 'integer', 'min:0', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'integer', 'min:0', 'exists:customers,id'],
            'archived_at' => ['nullable', 'date'],
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
        return $plural ? 'Agreements' : 'Agreement';
    }

    public function int_customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
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
