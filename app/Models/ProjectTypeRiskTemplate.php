<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class ProjectTypeRiskTemplate extends Model
{
    use HasFactory;

    protected $table = 'project_type_risk_templates';

    protected $fillable = ['name', 'project_type_id', 'scenariodescription', 'consequencedescription', 'probability_id', 'consequence_id', 'controls'];

    protected $appends = ['controls'];

    /**
     * @var array<int, int>
     */
    private array $pendingControlIds = [];

    private bool $hasPendingControlUpdate = false;

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
            'project_type_id' => ['nullable', 'integer', 'min:0', 'exists:project_types,id'],
            'scenariodescription' => ['nullable', 'string'],
            'consequencedescription' => ['nullable', 'string'],
            'probability_id' => ['nullable', 'integer', 'min:0', 'exists:probability_levels,id'],
            'consequence_id' => ['nullable', 'integer', 'min:0', 'exists:consequence_levels,id'],
            'controls' => ['sometimes', 'array'],
            'controls.*' => ['integer', 'min:1', 'exists:controls,id'],
        ];
    }

    public static function crudAppends(): array
    {
        return ['controls'];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'scenariodescription',
                'consequencedescription',
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

        static::saved(function (self $model): void {
            $model->syncPendingControls();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Risk Project Type Risk Templates' : 'Risk Project Type Risk Template';
    }

    /**
     * @return array<int, int>
     */
    public function getControlsAttribute(): array
    {
        return $this->int_controls()->pluck('controls.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param array<int, int|string>|int|string|null $value
     */
    public function setControlsAttribute(array|int|string|null $value): void
    {
        $rawControlIds = is_array($value)
            ? $value
            : ($value === null || $value === '' ? [] : [$value]);

        $this->pendingControlIds = collect($rawControlIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->hasPendingControlUpdate = true;
    }

    private function syncPendingControls(): void
    {
        if (! $this->hasPendingControlUpdate) {
            return;
        }

        $this->int_controls()->sync($this->pendingControlIds);

        $this->pendingControlIds = [];
        $this->hasPendingControlUpdate = false;
    }

    public function int_consequence(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'consequence_id');
    }

    public function int_probability(): BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'probability_id');
    }

    public function int_project_type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function int_control_project_type_risk_template(): HasMany
    {
        return $this->hasMany(ControlProjectTypeRiskTemplate::class, 'project_type_risk_template_id', 'id');
    }

    public function int_controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_project_type_risk_template', 'project_type_risk_template_id', 'control_id')
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
