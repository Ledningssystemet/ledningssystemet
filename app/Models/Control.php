<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class Control extends Model
{
    use HasFactory;

    protected $table = 'controls';

    protected $fillable = ['name', 'description', 'responsible_user_id', 'statusdescription', 'not_applicable_at', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'not_applicable_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'statusdescription' => ['nullable', 'string'],
            'not_applicable_at' => ['nullable', 'date'],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'statusdescription',
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
        return $plural ? 'Controls' : 'Control';
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_control_actions(): HasMany
    {
        return $this->hasMany(ControlAction::class, 'control_id', 'id');
    }

    public function int_control_requirements(): HasMany
    {
        return $this->hasMany(ControlRequirement::class, 'control_id', 'id');
    }

    public function int_control_risk_project_type_risk_template(): HasMany
    {
        return $this->hasMany(ControlRiskProjectTypeRiskTemplate::class, 'control_id', 'id');
    }

    public function int_control_risks(): HasMany
    {
        return $this->hasMany(ControlRisk::class, 'control_id', 'id');
    }

    public function int_requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'control_requirements', 'control_id', 'requirement_id');
    }

    public function int_risk_project_type_risk_templates(): BelongsToMany
    {
        return $this->belongsToMany(RiskProjectTypeRiskTemplate::class, 'control_risk_project_type_risk_template', 'control_id', 'risk_project_type_risk_template_id')
            ->withTimestamps();
    }

    public function int_risks(): BelongsToMany
    {
        return $this->belongsToMany(Risk::class, 'control_risks', 'control_id', 'risk_id');
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
