<?php

namespace App\Models;

use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Department extends Model
{

/* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if ($personalOnly)
         return [];

      if ((null != $user) && $user->cannot('update', Department::class))
         return [];

      $retval = [];
      $url = ((($user != null) && $user->can('index', get_called_class())) ||
         (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
         ? url()->query('/systemadmin/departments')
         : null;

      if (null == $department) {
         $table = (new self())->getTable();

         // Count departments missing at least one property classification
         $unclassified = Department::whereExists(function ($q) use ($table) {
            $q->selectRaw('1')
               ->from('properties')
               ->join('property_tabs', 'property_tabs.id', '=', 'properties.property_tab_id')
               ->leftJoin('object_properties as op', function ($join) use ($table) {
                  $join->on('op.property_id', '=', 'properties.id')
                     ->where('op.object_properties_type', self::class)
                     ->whereColumn('op.object_properties_id', $table.'.id');
               })
               ->where('property_tabs.context', self::class)
               ->whereNull('op.id');
         })->count();

         // Count departments with no users assigned
         $missingUsersCount = Department::doesntHave('int_users')->count();

         if ($unclassified)
            $retval[] = ['level' => 'warning', 'count' => $unclassified, 'text' => Department::getPrettyName($unclassified > 1).' '.__("without classification"), 'url' => $url];

         if ($missingUsersCount)
            $retval[] = ['level' => 'warning', 'count' => $missingUsersCount, 'text' => Department::getPrettyName($missingUsersCount > 1).' '.__("without any assigned users"), 'url' => $url];

      }

      return $retval;
   }

    use HasFactory;
    use HasStatus;

    protected $table = 'departments';

    protected $fillable = ['name', 'external_provider_group_id', 'parent_department_id', 'site_id', 'user_ids'];

    protected $appends = [
        'user_ids',
        'processcount',
        'departmentriskcount',
        'departmentfindingcount',
        'can_delete',
    ];

    /**
     * @var array<int, int>|null
     */
    private ?array $pendingUserIds = null;

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
            'external_provider_group_id' => ['nullable', 'integer', 'min:0', 'exists:external_provider_groups,id'],
            'parent_department_id' => ['nullable', 'integer', 'min:0', 'exists:departments,id'],
            'site_id' => ['nullable', 'integer', 'min:0', 'exists:sites,id'],
            'user_ids' => ['nullable', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
        ];
    }

    public static function applyCrudIndexFilters(mixed $query, Request $request): void
    {
        $query->withCount([
            'int_processes as processcount',
            'int_risks as departmentriskcount',
            'int_findings as departmentfindingcount',
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });

        static::saved(function (self $model): void {
            $model->syncUsersIfNeeded();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Departments' : 'Department';
    }

    public function int_external_provider_group(): BelongsTo
    {
        return $this->belongsTo(ExternalProviderGroup::class, 'external_provider_group_id');
    }

    public function int_parent_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function int_site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function int_compliance_evaluation_requirement_findings(): HasMany
    {
        return $this->hasMany(ComplianceEvaluationRequirementFinding::class, 'department_id', 'id');
    }

    public function int_department_user(): HasMany
    {
        return $this->hasMany(DepartmentUser::class, 'department_id', 'id');
    }

    public function int_departments(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id', 'id');
    }

    public function int_findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'department_id', 'id');
    }

    public function int_objectives(): HasMany
    {
        return $this->hasMany(Objective::class, 'department_id', 'id');
    }

    public function int_processes(): HasMany
    {
        return $this->hasMany(Process::class, 'department_id', 'id');
    }

    public function int_projects(): HasMany
    {
        return $this->hasMany(Project::class, 'department_id', 'id');
    }

    public function int_risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'department_id', 'id');
    }

    public function int_users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user', 'department_id', 'user_id')
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

    /**
     * @return array<int, int>
     */
    public function getUserIdsAttribute(): array
    {
        $users = $this->relationLoaded('int_users')
            ? $this->int_users
            : $this->int_users()->select('users.id')->get();

        return $users
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setUserIdsAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->pendingUserIds = [];

            return;
        }

        if (! is_array($value)) {
            return;
        }

        $normalized = collect($value)
            ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->pendingUserIds = $normalized;
    }

    public function getProcesscountAttribute(): int
    {
        if (array_key_exists('processcount', $this->attributes)) {
            return (int) $this->attributes['processcount'];
        }

        return $this->int_processes()->count();
    }

    public function getDepartmentriskcountAttribute(): int
    {
        if (array_key_exists('departmentriskcount', $this->attributes)) {
            return (int) $this->attributes['departmentriskcount'];
        }

        return $this->int_risks()->count();
    }

    public function getDepartmentfindingcountAttribute(): int
    {
        if (array_key_exists('departmentfindingcount', $this->attributes)) {
            return (int) $this->attributes['departmentfindingcount'];
        }

        return $this->int_findings()->count();
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->processcount === 0
            && $this->departmentriskcount === 0
            && $this->departmentfindingcount === 0;
    }

    private function syncUsersIfNeeded(): void
    {
        if ($this->pendingUserIds === null) {
            return;
        }

        $currentUserIds = $this->int_users()->pluck('users.id')->map(static fn (mixed $id): int => (int) $id)->all();
        $nextUserIds = $this->pendingUserIds;

        $this->int_users()->sync($nextUserIds);

        $touchedUserIds = array_values(array_unique(array_merge($currentUserIds, $nextUserIds)));
        if ($touchedUserIds !== []) {
            User::query()->whereIn('id', $touchedUserIds)->update(['updated_at' => now()]);
        }

        $this->pendingUserIds = null;
    }

    protected function resolveStatus(): array
   {
      if(!$this->classified)
         return $this->defaultStatus('warning', __("The department has not been classified regarding information security"));

      if(!$this->int_users()->exists())
         return $this->defaultStatus('warning', __("The department has no assigned users"));

      if($this->external_provider_group_id)
         return $this->defaultStatus('success', '');

      return $this->defaultStatus('unknown', '');
   }

}

