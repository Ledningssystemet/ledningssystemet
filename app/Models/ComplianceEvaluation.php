<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

class ComplianceEvaluation extends Model
{
    use HasFactory;

    protected $table = 'compliance_evaluations';

    protected $fillable = ['name', 'startdate', 'description', 'participants', 'summary', 'finished', 'archived'];

    protected $appends = ['statistics', 'requirement_sources'];

    protected function casts(): array
    {
        return [
            'startdate' => 'date',
            'finished' => 'datetime',
            'archived' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'startdate' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'participants' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'finished' => ['nullable', 'date'],
            'archived' => ['nullable', 'date'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'participants',
                'summary',
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
        return $plural ? 'Compliance Evaluations' : 'Compliance Evaluation';
    }

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        if ($request->boolean('hidearchived', true)) {
            $query->whereNull('archived');
        }
    }

    public function getStatisticsAttribute(): array
    {
        $rows = DB::table('compliance_evaluation_requirement')
            ->where('compliance_evaluation_id', $this->id)
            ->select(DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN evaluated=1 AND applicable=1 THEN 1 ELSE 0 END) as pass'), DB::raw('SUM(CASE WHEN evaluated=1 AND applicable=0 THEN 1 ELSE 0 END) as na'), DB::raw('SUM(CASE WHEN evaluated=0 THEN 1 ELSE 0 END) as open'))
            ->first();

        return [
            'requirements' => (int) ($rows->total ?? 0),
            'pass' => (int) ($rows->pass ?? 0),
            'fail' => 0,
            'na' => (int) ($rows->na ?? 0),
            'open' => (int) ($rows->open ?? 0),
        ];
    }

    public function getRequirementSourcesAttribute(): array
    {
        return DB::table('compliance_evaluation_requirement_source')
            ->leftJoin('requirement_sources', 'requirement_sources.id', '=', 'compliance_evaluation_requirement_source.requirement_source_id')
            ->where('compliance_evaluation_id', $this->id)
            ->orderBy('requirement_sources.reference')
            ->select(['compliance_evaluation_requirement_source.id', 'requirement_sources.name', 'requirement_sources.reference', 'compliance_evaluation_requirement_source.requirement_source_id'])
            ->get()
            ->toArray();
    }

    public function int_compliance_evaluation_requirement(): HasMany
    {
        return $this->hasMany(ComplianceEvaluationRequirement::class, 'compliance_evaluation_id', 'id');
    }

    public function int_compliance_evaluation_requirement_source(): HasMany
    {
        return $this->hasMany(ComplianceEvaluationRequirementSource::class, 'compliance_evaluation_id', 'id');
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
