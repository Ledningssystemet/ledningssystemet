<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

class Finding extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', Finding::class))
         return [];

      $departments = null;
      if($department)
         $departments = [$department->id];
      else if($user)
         $departments = $user->int_departments()->pluck('departments.id');

      $count = (null == $departments) ? Finding::whereNull('finished_at')->where('nonconformity', true)->count() : Finding::whereNull('finished_at')->where('nonconformity', true)->whereIn('department_id', $departments)->count();
      if($count)
         $retval[] = ['level' => (null == $user) ? 'warning' : 'danger', 'count' => $count, 'text' => __("Non-conformitites").' '.__("pending assessment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/findings') : null];

      $count = (null == $departments) ? Finding::whereNull('finished_at')->where('nonconformity', false)->count() : Finding::whereNull('finished_at')->where('nonconformity', false)->whereIn('department_id', $departments)->count();
      if($count)
         $retval[] = ['level' => (null == $user) ? 'info' : 'warning', 'count' => $count, 'text' => __("Observation").' '.__("pending assessment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/assessment/findings') : null];

      return $retval;
   }

    use HasFactory;
    use HasStatus;

    protected $table = 'findings';

    protected $fillable = ['name', 'description', 'department_id', 'finished_at', 'nonconformity', 'consequence', 'rootcause', 'immediateaction', 'preventativeaction', 'compliance_evaluation_requirement_finding_id', 'context_type', 'context_id', 'created_by', 'estimated_cost', 'distribution_analysis'];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
            'nonconformity' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'department_id' => ['required', 'integer', 'min:0', 'exists:departments,id'],
            'finished_at' => ['nullable', 'date'],
            'nonconformity' => ['required', 'boolean'],
            'consequence' => ['nullable', 'string'],
            'rootcause' => ['nullable', 'string'],
            'immediateaction' => ['nullable', 'string'],
            'preventativeaction' => ['nullable', 'string'],
            'compliance_evaluation_requirement_finding_id' => ['nullable', 'integer', 'min:0', 'exists:compliance_evaluation_requirement_findings,id'],
            'context_type' => ['nullable', 'string', 'max:255'],
            'context_id' => ['nullable', 'integer', 'min:0'],
            'created_by' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'estimated_cost' => ['nullable', 'integer', 'min:0'],
            'distribution_analysis' => ['nullable', 'string'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'description',
                'consequence',
                'rootcause',
                'immediateaction',
                'preventativeaction',
                'context_type',
                'distribution_analysis',
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
        return $plural ? 'Findings' : 'Finding';
    }

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        $showUnhandled = $request->boolean('show_unhandled', false);
        $showHandled = $request->boolean('show_handled', false);

        if ($showUnhandled && ! $showHandled) {
            $query->whereNull('finished_at');
        } elseif ($showHandled && ! $showUnhandled) {
            $query->whereNotNull('finished_at');
        }
        // If both are set or neither, no filter is applied (show all)
    }

    public function int_compliance_evaluation_requirement_finding(): BelongsTo
    {
        return $this->belongsTo(ComplianceEvaluationRequirementFinding::class, 'compliance_evaluation_requirement_finding_id');
    }

    public function int_created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function int_control_action_mappings(): HasMany
    {
        return $this->hasMany(ControlActionMapping::class, 'finding_id', 'id');
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

    protected function resolveStatus(): array
   {
      if($this->finished_at)
         return $this->defaultStatus('success', '');

      if($this->nonconformity)
         return $this->defaultStatus('danger', __("This non-conformity has not been assessed"));

      return $this->defaultStatus('warning', __("This observation has not been assessed"));
   }

}

