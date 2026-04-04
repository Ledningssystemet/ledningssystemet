<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'supplier_id', 'confidentiality_class_id', 'integrity_class_id', 'availability_class_id', 'mtd', 'rpo', 'site_id'];

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
            'supplier_id' => ['nullable', 'integer', 'min:0', 'exists:suppliers,id'],
            'confidentiality_class_id' => ['nullable', 'integer', 'min:0', 'exists:confidentiality_classes,id'],
            'integrity_class_id' => ['nullable', 'integer', 'min:0', 'exists:integrity_classes,id'],
            'availability_class_id' => ['nullable', 'integer', 'min:0', 'exists:availability_classes,id'],
            'mtd' => ['nullable', 'integer', 'min:0'],
            'rpo' => ['nullable', 'integer', 'min:0'],
            'site_id' => ['nullable', 'integer', 'min:0', 'exists:sites,id'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
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
        return $plural ? 'Assets' : 'Asset';
    }

    public function int_site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
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

    public function int_supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function int_asset_asset_dependancy_by_dependant_asset(): HasMany
    {
        return $this->hasMany(AssetAssetDependancy::class, 'dependant_asset_id', 'id');
    }

    public function int_asset_asset_dependancy_by_depending_asset(): HasMany
    {
        return $this->hasMany(AssetAssetDependancy::class, 'depending_asset_id', 'id');
    }

    public function int_asset_information_type(): HasMany
    {
        return $this->hasMany(AssetInformationType::class, 'asset_id', 'id');
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
}
