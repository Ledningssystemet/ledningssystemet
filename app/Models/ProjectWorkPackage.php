<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectWorkPackage extends Model
{
    protected ?array $activityIdsToSync = null;

    protected $attributes = ['description' => null, 'completed_at' => null, 'start_date' => null, 'end_date' => null];

    protected $appends = ['access', 'activities'];

    protected $visible = [
        'id', 'project_id', 'name', 'description', 'completed_at', 'start_date', 'end_date',
        'created_at', 'updated_at', 'access', 'activities',
    ];

    protected $fillable = ['project_id', 'name', 'description', 'completed_at', 'activities', 'start_date', 'end_date'];

    public $actions = ['createactivity'];

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Work packages') : __('Work package');
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            // Serialize membership and completion changes with activity updates.
            Project::whereKey($this->getOriginal('project_id') ?? $this->project_id)->lockForUpdate()->first();

            return parent::save($options);
        });
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $package): void {
            Validator::make($package->getAttributes(), $package->getValidationRules())->validate();
            $project = Project::findOrFail($package->project_id);
            if (auth()->user() && ! $project->canWrite(auth()->user())) {
                abort(403);
            }
            if ($project->archived_at) {
                abort(400, __('You cannot modify work packages for an archived project'));
            }
            if ($package->exists && $package->isDirty('project_id')) {
                abort(422, __('The project of a work package cannot be changed'));
            }
            $wasCompleted = $package->exists && self::whereKey($package->id)->value('completed_at');
            if ($wasCompleted && ! ($package->isDirty('completed_at') && $package->completed_at === null)) {
                throw ValidationException::withMessages([
                    'completed_at' => __('Reopen the work package before changing it'),
                ]);
            }

            $ids = $package->activityIdsToSync ?? $package->int_activities()->pluck('project_activities.id')->all();
            Validator::make(['activities' => $ids], [
                'activities.*' => [Rule::exists('project_activities', 'id')->where('project_id', $package->project_id)],
            ])->validate();

            if ($package->completed_at && ($ids === [] || ProjectActivity::whereIn('id', $ids)->whereNull('completed_at')->exists())) {
                throw ValidationException::withMessages([
                    'completed_at' => __('A work package can only be completed when it contains activities and all of them are completed'),
                ]);
            }
        });

        static::saved(function (self $package): void {
            if ($package->activityIdsToSync !== null) {
                $package->int_activities()->sync($package->activityIdsToSync);
                $package->activityIdsToSync = null;
                $package->unsetRelation('int_activities');
            }
        });

        static::deleting(function (self $package): void {
            if ($package->int_project?->archived_at) {
                abort(400, __('You cannot delete work packages for an archived project'));
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

    public function getActivitiesAttribute(): array
    {
        return $this->int_activities->map(fn (ProjectActivity $activity): array => [
            'id' => $activity->id, 'name' => $activity->name, 'completed_at' => $activity->completed_at,
        ])->all();
    }

    public function setActivitiesAttribute($value): void
    {
        Validator::make(['activities' => $value], [
            'activities' => 'array',
            'activities.*' => ['integer', 'distinct'],
        ])->validate();
        $this->activityIdsToSync = $value;
    }

    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'completed_at' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => $this->start_date ? 'nullable|date|after_or_equal:start_date' : 'nullable|date',
        ];
    }

    public function createactivity(): ProjectActivity
    {
        abort_unless(auth()->user()->can('update', $this) && auth()->user()->can('create', ProjectActivity::class), 403);

        return DB::transaction(function (): ProjectActivity {
            Project::whereKey($this->project_id)->lockForUpdate()->firstOrFail();
            $package = self::findOrFail($this->id);
            if ($package->completed_at) {
                abort(422, __('Reopen the work package before changing it'));
            }

            $activity = new ProjectActivity;
            $activity->fill(request()->only($activity->getFillable()));
            $activity->project_id = $package->project_id;
            $activity->save();
            $package->int_activities()->attach($activity->id);
            $package->touch();

            return $activity;
        });
    }

    public function int_project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function int_activities(): BelongsToMany
    {
        return $this->belongsToMany(ProjectActivity::class, 'project_work_package_activities', 'project_work_package_id', 'project_activity_id');
    }
}
