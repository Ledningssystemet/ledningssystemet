<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Risk extends Model
{
    use HasFactory;

    protected $table = 'risks';

    protected $fillable = ['name', 'context_type', 'department_id', 'context_id', 'scenariodescription', 'consequencedescription', 'riskowner_id', 'replacing_id', 'replacedby_id', 'assessed_at', 'replaced_at', 'created_by', 'probability_id', 'consequence_id', 'assessmentcomment', 'risk_project_id', 'post_probability_id', 'post_consequence_id'];


    protected function name(): Attribute
    {
        // Get context object if it exists
        $contextName = $this->int_context ? $this->int_context->name : null;

        // Return $this->name but replace {name} with name of the context object.
        // If context object does not exist, return $this->name as is.

        if(null == $contextName) {
            return Attribute::make(
                get: fn ($value) => $value,
            );
        }

        return Attribute::make(
            get: fn ($value) => str_replace('{name}', $contextName, $value),
        );
    }

    protected function casts(): array
    {
        return [
            'assessed_at' => 'datetime',
            'replaced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'context_type' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', 'integer', 'min:0', 'exists:departments,id'],
            'context_id' => ['nullable', 'integer', 'min:0'],
            'scenariodescription' => ['nullable', 'string'],
            'consequencedescription' => ['nullable', 'string'],
            'riskowner_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'replacing_id' => ['nullable', 'integer', 'min:0', 'exists:risks,id'],
            'replacedby_id' => ['nullable', 'integer', 'min:0', 'exists:risks,id'],
            'assessed_at' => ['nullable', 'date'],
            'replaced_at' => ['nullable', 'date'],
            'created_by' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'probability_id' => ['nullable', 'integer', 'min:0', 'exists:probability_levels,id'],
            'consequence_id' => ['nullable', 'integer', 'min:0', 'exists:consequence_levels,id'],
            'assessmentcomment' => ['nullable', 'string'],
            'risk_project_id' => ['nullable', 'integer', 'min:0', 'exists:risk_projects,id'],
            'post_probability_id' => ['nullable', 'integer', 'min:0', 'exists:probability_levels,id'],
            'post_consequence_id' => ['nullable', 'integer', 'min:0', 'exists:consequence_levels,id'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'context_type',
                'scenariodescription',
                'consequencedescription',
                'assessmentcomment',
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
        return $plural ? 'Risks' : 'Risk';
    }

    public function int_consequence(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'consequence_id');
    }

    public function int_created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function int_post_consequence(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'post_consequence_id');
    }

    public function int_post_probability(): BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'post_probability_id');
    }

    public function int_probability(): BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'probability_id');
    }

    public function int_replacedby(): BelongsTo
    {
        return $this->belongsTo(Risk::class, 'replacedby_id');
    }

    public function int_replacing(): BelongsTo
    {
        return $this->belongsTo(Risk::class, 'replacing_id');
    }

    public function int_risk_project(): BelongsTo
    {
        return $this->belongsTo(RiskProject::class, 'risk_project_id');
    }

    public function int_riskowner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'riskowner_id');
    }

    public function int_control_action_mappings(): HasMany
    {
        return $this->hasMany(ControlActionMapping::class, 'risk_id', 'id');
    }

    public function int_control_risks(): HasMany
    {
        return $this->hasMany(ControlRisk::class, 'risk_id', 'id');
    }

    public function int_risks_by_replacedby(): HasMany
    {
        return $this->hasMany(Risk::class, 'replacedby_id', 'id');
    }

    public function int_risks_by_replacing(): HasMany
    {
        return $this->hasMany(Risk::class, 'replacing_id', 'id');
    }

    public function int_controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_risks', 'risk_id', 'control_id');
    }

    public function int_context(): MorphTo
    {
        return $this->morphTo('context', 'context_type', 'context_id');
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

    public function int_vector_embeddings_as_embeddable(): MorphMany
    {
        return $this->morphMany(VectorEmbedding::class, 'embeddable', 'embeddable_type', 'embeddable_id');
    }
}
