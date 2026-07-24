<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

      $maxAgeDays = max(0, (int) config('ledningssystemet.requirement_source_approval_max_age_days', 365));
      $staleBefore = $maxAgeDays > 0 ? now()->subDays($maxAgeDays) : null;

      $needsApprovalCount = (clone $baseQuery)
         ->where(function ($q) use ($staleBefore) {
            $q->whereNull('approved_at')
               ->orWhereColumn('requirement_sources.updated_at', '>', 'requirement_sources.approved_at')
               ->orWhereHas('int_requirements', function ($rq) {
                   $rq->where(function ($rq2): void {
                       $rq2->whereColumn('requirements.updated_at', '>', 'requirement_sources.approved_at')
                           ->orWhereColumn('requirements.created_at', '>', 'requirement_sources.approved_at');
                   });
               });

            if ($staleBefore !== null) {
               $q->orWhere(function ($staleQuery) use ($staleBefore): void {
                   $staleQuery->whereNotNull('requirement_sources.approved_at')
                       ->where('requirement_sources.approved_at', '<', $staleBefore);
               });
            }
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
    protected $appends = ['needsapproval', 'applicabilitymissingcount', 'approval_reason_types'];

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
            if ($model->isDirty('approved_at') && $model->approved_at !== null) {
                $user = auth()->user();
                if ($user === null || (int) $model->responsible_user_id !== (int) $user->id) {
                    throw ValidationException::withMessages([
                        'approved_at' => [__('Only the responsible user can approve this requirement source.')],
                    ]);
                }
            }

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

        if($this->needsApproval())
            return $this->defaultStatus('warning', $this->approvalReasonDescription());

        return $this->defaultStatus('success', '');
    }

    public function getNeedsapprovalAttribute(): bool
    {
        return $this->needsApproval();
    }

    /**
     * @return array<int, string>
     */
    public function getApprovalReasonTypesAttribute(): array
    {
        [$approvedAt, $updatedAt] = $this->resolveApprovalTimestamps();
        $reasonTypes = [];

        if ($approvedAt === null) {
            return ['source_updated'];
        }

        $maxAgeDays = max(0, (int) config('ledningssystemet.requirement_source_approval_max_age_days', 365));
        if ($maxAgeDays > 0 && $approvedAt->copy()->addDays($maxAgeDays)->isPast()) {
            $reasonTypes[] = 'stale_approval';
        }

        if ($updatedAt !== null && $updatedAt->gt($approvedAt)) {
            $reasonTypes[] = 'source_updated';
        }

        $requirementsChanged = $this->int_requirements()
            ->where(function ($query) use ($approvedAt): void {
                $query->where('requirements.updated_at', '>', $approvedAt)
                    ->orWhere('requirements.created_at', '>', $approvedAt);
            })
            ->exists();

        if ($requirementsChanged) {
            $reasonTypes[] = 'requirements_changed';
        }

        return array_values(array_unique($reasonTypes));
    }

    public function getApplicabilitymissingcountAttribute(): int
    {
        return $this->int_requirements()->whereNull('applicable')->count();
    }

    public function needsApproval(): bool
    {
        return $this->approval_reason_types !== [];
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function resolveApprovalTimestamps(): array
    {
        $attributes = $this->getAttributes();
        $approvedAt = $this->approved_at;
        $updatedAt = $this->updated_at;

        if ((! array_key_exists('approved_at', $attributes) || ! array_key_exists('updated_at', $attributes)) && $this->exists) {
            $fresh = self::query()
                ->select(['id', 'approved_at', 'updated_at'])
                ->find($this->getKey());

            if ($fresh instanceof self) {
                $approvedAt = $fresh->approved_at;
                $updatedAt = $fresh->updated_at;
            }
        }

        return [$approvedAt, $updatedAt];
    }

    private function approvalReasonDescription(): string
    {
        $reasons = [];

        foreach ($this->approval_reason_types as $reasonType) {
            if ($reasonType === 'stale_approval') {
                $reasons[] = __('pages.requirement_sources.reason_stale_approval', [
                    'days' => max(0, (int) config('ledningssystemet.requirement_source_approval_max_age_days', 365)),
                ]);
                continue;
            }

            if ($reasonType === 'requirements_changed') {
                $reasons[] = __('pages.requirement_sources.reason_requirements_changed');
                continue;
            }

            if ($reasonType === 'source_updated') {
                $reasons[] = __('pages.requirement_sources.reason_source_updated');
            }
        }

        return implode(' ', $reasons);
    }
}
