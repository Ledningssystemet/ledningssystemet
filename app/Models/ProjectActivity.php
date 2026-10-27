<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectActivity extends Model
{
    protected ?array $riskIdsToSync = null;

    protected $appends = ['access', 'risks'];

    protected $visible = [
        'id', 'name', 'description', 'project_id', 'responsible_user_id',
        'startdate', 'due', 'estimated_cost', 'estimated_hours', 'completed_at', 'assessmentcomment',
        'created_at', 'updated_at', 'access', 'risks', 'recurring_project_activity_id',
    ];

    protected $fillable = [
        'name', 'description', 'project_id', 'responsible_user_id',
        'startdate', 'due', 'estimated_cost', 'estimated_hours', 'completed_at', 'assessmentcomment', 'risks', 'recurring_project_activity_id',
    ];

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Project activities') : __('Project activity');
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            Project::whereKey($this->getOriginal('project_id') ?? $this->project_id)->lockForUpdate()->first();

            return parent::save($options);
        });
    }

    public function delete()
    {
        return DB::transaction(function () {
            Project::whereKey($this->project_id)->lockForUpdate()->first();

            return parent::delete();
        });
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $activity): void {
            Validator::make($activity->getAttributes(), $activity->getValidationRules())->validate();

            $project = Project::findOrFail($activity->project_id);
            if (auth()->user() && ! $project->canWrite(auth()->user())) {
                abort(403);
            }
            if ($project->archived_at) {
                abort(400, __('You cannot modify activities for an archived project'));
            }
            if ($activity->exists && $activity->isDirty('project_id')) {
                abort(422, __('The project of an activity cannot be changed'));
            }
            if ($activity->exists && $activity->isDirty('recurring_project_activity_id')) {
                throw ValidationException::withMessages([
                    'recurring_project_activity_id' => __('The recurrence of an activity cannot be changed'),
                ]);
            }
            if ($activity->recurring_project_activity_id && ! $activity->completed_at
                && self::where('recurring_project_activity_id', $activity->recurring_project_activity_id)
                    ->whereNull('completed_at')->when($activity->exists, fn (Builder $query) => $query->whereKeyNot($activity->id))->exists()) {
                throw ValidationException::withMessages([
                    'completed_at' => __('An unfinished activity already exists for this recurrence'),
                ]);
            }
            if ($activity->exists && ! $activity->completed_at && $activity->int_work_packages()->whereNotNull('completed_at')->exists()) {
                throw ValidationException::withMessages([
                    'completed_at' => __('Reopen all completed work packages containing this activity before reopening it'),
                ]);
            }
            if ($activity->riskIdsToSync !== null) {
                Validator::make(['risks' => $activity->riskIdsToSync], [
                    'risks.*' => [Rule::exists('project_risks', 'id')->where('project_id', $activity->project_id)],
                ])->validate();
            }
        });

        static::saved(function (self $activity): void {
            if ($activity->riskIdsToSync !== null) {
                $activity->int_risks()->sync($activity->riskIdsToSync);
                $activity->riskIdsToSync = null;
                $activity->unsetRelation('int_risks');
            }
        });

        static::deleting(function (self $activity): void {
            if ($activity->int_project?->archived_at) {
                abort(400, __('You cannot delete activities for an archived project'));
            }
            if ($activity->int_work_packages()->whereNotNull('completed_at')->exists()) {
                abort(422, __('Reopen all completed work packages containing this activity before deleting it'));
            }
        });
    }

    public function getAccessAttribute($user = null): ?array
    {
        $user ??= auth()->user();
        if (! $user) {
            return null;
        }

        return [
            'create' => $user->can('create', self::class),
            'read' => $user->can('view', $this),
            'update' => $user->can('update', $this),
            'delete' => $user->can('delete', $this),
        ];
    }

    public function getRisksAttribute(): array
    {
        return $this->int_risks->map(fn (ProjectRisk $risk): array => [
            'id' => $risk->id, 'name' => $risk->name,
        ])->all();
    }

    public function setRisksAttribute($value): void
    {
        Validator::make(['risks' => $value], [
            'risks' => 'array',
            'risks.*' => ['integer', 'distinct'],
        ])->validate();
        $this->riskIdsToSync = $value;
    }

    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'startdate' => 'nullable|date',
            'due' => $this->startdate ? 'nullable|date|after_or_equal:startdate' : 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0|max:99999999.99',
            'estimated_hours' => 'nullable|integer|min:0|max:4294967295',
            'completed_at' => 'nullable|date',
            'assessmentcomment' => 'nullable|string',
            'recurring_project_activity_id' => ['nullable', 'integer', Rule::exists('recurring_project_activities', 'id')->where('project_id', $this->project_id)],
        ];
    }

   public function scopeVisibleTo(Builder $query, User $user): Builder
   {
      if ($user->hasAnyPermission(['superadmin.edit'])) {
         return $query;
      }

      // Check if there is a filter for project_id in the request and if the user has access to that project
      if( request()->has('filter[project_id]')) {
         $query->where('project_id', request()->input('filter[project_id]'))
         ->whereHas('int_project', function (Builder $query) use ($user) {;
               $query->whereHas('int_users', function (Builder $query) use ($user) {
                  $query->where('users.id', $user->id);
               })
               ->orWhere('responsible_user_id', $user->id);
            });
      }

      return $query;
   }


    public function int_project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function int_recurring_project_activity(): BelongsTo
    {
        return $this->belongsTo(RecurringProjectActivity::class, 'recurring_project_activity_id');
    }

    public function int_risks(): BelongsToMany
    {
        return $this->belongsToMany(ProjectRisk::class, 'project_risk_project_activities', 'project_activity_id', 'project_risk_id')->distinct();
    }

    public function int_work_packages(): BelongsToMany
    {
        return $this->belongsToMany(ProjectWorkPackage::class, 'project_work_package_activities', 'project_activity_id', 'project_work_package_id');
    }
}
