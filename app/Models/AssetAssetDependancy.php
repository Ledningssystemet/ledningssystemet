


<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
class AssetAssetDependancy extends Model
{
    use HasFactory;
    protected $table = 'asset_asset_dependancy';
    protected $fillable = ['dependant_asset_id', 'depending_asset_id', 'inherit_confidentiality', 'inherit_integrity', 'inherit_availability', 'description'];

    protected function casts(): array
    {
        return [
            'inherit_confidentiality' => 'boolean',
            'inherit_integrity' => 'boolean',
            'inherit_availability' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'dependant_asset_id' => ['required', 'integer', 'min:0', 'exists:assets,id'],
            'depending_asset_id' => ['required', 'integer', 'min:0', 'exists:assets,id'],
            'inherit_confidentiality' => ['required', 'boolean'],
            'inherit_integrity' => ['required', 'boolean'],
            'inherit_availability' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
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
        return $plural ? 'Asset Asset Dependancies' : 'Asset Asset Dependancy';
    }

    public function int_dependant_asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'dependant_asset_id');
    }

    public function int_depending_asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'depending_asset_id');
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
