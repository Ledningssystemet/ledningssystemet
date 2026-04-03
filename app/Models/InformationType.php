


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
class InformationType extends Model
{
    use HasFactory;
    protected $table = 'information_types';
    protected $fillable = ['name', 'description', 'responsible_user_id', 'confidentiality_class_id', 'integrity_class_id', 'availability_class_id', 'retention', 'piidescription', 'confidentiality_ground_id', 'diary_id', 'archivingdescription', 'archiveshippingtime', 'archivemedia', 'sortinginformation'];

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
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'confidentiality_class_id' => ['nullable', 'integer', 'min:0', 'exists:confidentiality_classes,id'],
            'integrity_class_id' => ['nullable', 'integer', 'min:0', 'exists:integrity_classes,id'],
            'availability_class_id' => ['nullable', 'integer', 'min:0', 'exists:availability_classes,id'],
            'retention' => ['nullable', 'integer', 'min:0'],
            'piidescription' => ['nullable', 'string'],
            'confidentiality_ground_id' => ['nullable', 'integer', 'min:0', 'exists:confidentiality_grounds,id'],
            'diary_id' => ['nullable', 'integer', 'min:0', 'exists:diaries,id'],
            'archivingdescription' => ['nullable', 'string'],
            'archiveshippingtime' => ['nullable', 'integer', 'min:0'],
            'archivemedia' => ['nullable', 'string', 'max:255'],
            'sortinginformation' => ['nullable', 'string', 'max:255'],
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
        return $plural ? 'Information Types' : 'Information Type';
    }

    public function int_availability_class(): BelongsTo
    {
        return $this->belongsTo(AvailabilityClass::class, 'availability_class_id');
    }

    public function int_confidentiality_class(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityClass::class, 'confidentiality_class_id');
    }

    public function int_integrity_class(): BelongsTo
    {
        return $this->belongsTo(IntegrityClass::class, 'integrity_class_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_confidentiality_ground(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityGround::class, 'confidentiality_ground_id');
    }

    public function int_diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class, 'diary_id');
    }

    public function int_asset_information_type(): HasMany
    {
        return $this->hasMany(AssetInformationType::class, 'information_type_id', 'id');
    }

    public function int_data_category_information_type(): HasMany
    {
        return $this->hasMany(DataCategoryInformationType::class, 'information_type_id', 'id');
    }

    public function int_information_type_process_activity(): HasMany
    {
        return $this->hasMany(InformationTypeProcessActivity::class, 'information_type_id', 'id');
    }

    public function int_information_type_recipient_category(): HasMany
    {
        return $this->hasMany(InformationTypeRecipientCategory::class, 'information_type_id', 'id');
    }

    public function int_information_type_subject_category(): HasMany
    {
        return $this->hasMany(InformationTypeSubjectCategory::class, 'information_type_id', 'id');
    }

    public function int_data_categories(): BelongsToMany
    {
        return $this->belongsToMany(DataCategory::class, 'data_category_information_type', 'information_type_id', 'data_category_id');
    }

    public function int_process_activities(): BelongsToMany
    {
        return $this->belongsToMany(ProcessActivity::class, 'information_type_process_activity', 'information_type_id', 'process_activity_id');
    }

    public function int_recipient_categories(): BelongsToMany
    {
        return $this->belongsToMany(RecipientCategory::class, 'information_type_recipient_category', 'information_type_id', 'recipient_category_id');
    }

    public function int_subject_categories(): BelongsToMany
    {
        return $this->belongsToMany(SubjectCategory::class, 'information_type_subject_category', 'information_type_id', 'subject_category_id');
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
