<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecurringProjectActivity extends Model
{
    protected $appends = ['access', 'responsible_user_name'];

    protected $visible = [
        'id', 'project_id', 'name', 'description', 'responsible_user_id', 'responsible_user_name',
        'estimated_cost', 'estimated_hours', 'frequency', 'weekday', 'months',
        'day_of_month', 'last_generated_on', 'access',
    ];

    protected $fillable = [
        'project_id', 'name', 'description', 'responsible_user_id',
        'estimated_cost', 'estimated_hours', 'frequency', 'weekday', 'months', 'day_of_month',
    ];

    protected $attributes = [
        'months' => '[1,2,3,4,5,6,7,8,9,10,11,12]',
    ];

    protected $casts = [
        'months' => 'array', 'weekday' => 'integer', 'day_of_month' => 'integer',
    ];

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Recurring project activities') : __('Recurring project activity');
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
            $this->assertWritable();
            $this->int_activities()->update(['recurring_project_activity_id' => null]);

            return parent::delete();
        });
    }

    protected static function booted(): void
    {
        static::saving(function (self $activity): void {
            Validator::make([
                ...$activity->getAttributes(), 'months' => $activity->months,
            ], $activity->getValidationRules())->validate();
            $activity->assertWritable();
            if ($activity->exists && $activity->isDirty('project_id')) {
                abort(422, __('The project of an activity cannot be changed'));
            }
        });
    }

    private function assertWritable(): void
    {
        $project = Project::findOrFail($this->project_id);
        if (auth()->user() && ! $project->canWrite(auth()->user())) {
            abort(403);
        }
        if ($project->archived_at) {
            abort(400, __('You cannot modify activities for an archived project'));
        }
    }

    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'estimated_cost' => 'nullable|numeric|min:0|max:99999999.99',
            'estimated_hours' => 'nullable|integer|min:0|max:4294967295',
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'weekday' => 'nullable|required_if:frequency,weekly|integer|between:1,7',
            'months' => 'required|array|min:1|max:12',
            'months.*' => 'required|integer|between:1,12|distinct',
            'day_of_month' => 'nullable|integer|between:1,31',
        ];
    }

    public function getAccessAttribute(): ?array
    {
        $user = auth()->user();
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

    public function getResponsibleUserNameAttribute(): ?string
    {
        return $this->responsible_user_id ? $this->int_responsible_user?->name : null;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->with('int_responsible_user:id,name');

        if ($user->hasAnyPermission(['superadmin.edit'])) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('responsible_user_id', $user->id)
                ->orWhereHas('int_project', fn (Builder $project) => $project
                    ->where('responsible_user_id', $user->id)
                    ->orWhereHas('int_users', fn (Builder $users) => $users->where('users.id', $user->id)));
        });
    }

    public function isDueOn(CarbonInterface $date): bool
    {
        return match ($this->frequency) {
            'daily' => true,
            'weekly' => $date->dayOfWeekIso === $this->weekday,
            'monthly' => in_array($date->month, array_map('intval', $this->months), true)
                && $date->day === min($this->day_of_month ?? $date->daysInMonth, $date->daysInMonth),
        };
    }

    public function int_project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class, 'recurring_project_activity_id');
    }
}
