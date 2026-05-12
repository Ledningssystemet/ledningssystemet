<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;

class RequirementSource extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      if(null != $department)
         return [];

      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', RequirementSource::class))
         return [];

      $count = (null == $department) ? RequirementSource::whereNull('responsible_user_id')->count() : 0;
      if(!$personalOnly && ($count > 0))
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => RequirementSource::getPrettyName($count > 1).' '.__("without assignment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/requirements') : null];

      $baseQuery = RequirementSource::query();

      if ($user) {
         $baseQuery->where('responsible_user_id', $user->id);
      }

      $count = (clone $baseQuery)
         ->whereHas('int_requirements', function ($q) {
            $q->whereNull('applicable');
         })
         ->count();

      $needsApprovalCount = (clone $baseQuery)
         ->where(function ($q) {
            // Motsvarar: approved_at är null OCH det finns minst ett requirement
            $q->where(function ($q2) {
               $q2->whereNull('approved_at')
                  ->whereHas('int_requirements');
            })
               // Motsvarar: minst ett requirement uppdaterat efter source-approved_at
               ->orWhereHas('int_requirements', function ($rq) {
                  $rq->whereColumn('requirements.updated_at', '>', 'requirement_sources.approved_at');
               });
         })
         ->count();

      if($count > 0)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $count, 'text' => RequirementSource::getPrettyName($count > 1).' '. __("pending applicability determination"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/requirements') : null];

      if($needsApprovalCount > 0)
         $retval[] = ['level' => 'warning', 'count' => $needsApprovalCount, 'text' => RequirementSource::getPrettyName($needsApprovalCount > 1).' '. __("needs approval"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/requirements') : null];

      return $retval;
   }

    use HasFactory;
    use HasStatus;

    protected $table = 'requirement_sources';

    protected $fillable = ['name', 'reference', 'description', 'responsible_user_id', 'approved_at', 'max_sanction_fee'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'reference' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'min:0', 'exists:users,id'],
            'approved_at' => ['nullable', 'date'],
            'max_sanction_fee' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'reference',
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
        return $plural ? 'Requirement Sources' : 'Requirement Source';
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_compliance_evaluation_requirement_source(): HasMany
    {
        return $this->hasMany(ComplianceEvaluationRequirementSource::class, 'requirement_source_id', 'id');
    }

    public function int_requirements(): HasMany
    {
        return $this->hasMany(Requirement::class, 'requirement_source_id', 'id');
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

    protected function resolveStatus(): array
    {
        if(!$this->responsible_user_id)
            return $this->defaultStatus('danger', __("A responsible user has not been assigned"));

        if(0 < $this->int_requirements()->whereNull('applicable')->count())
            return $this->defaultStatus('danger', __("There are requirements which has not been assessed regarding applicability"));

        if(($this->approved_at === null) || (strtotime($this->updated_at >strtotime($this->approved_at))))
            return $this->defaultStatus('warning', __("This requirement source needs approval"));

        return $this->defaultStatus('success', '');
    }
}
