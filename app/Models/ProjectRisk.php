<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;
use Ledningssystemet\Ledningssystemet\Models\Concerns\DefersRelationAttributeSync;
use Ledningssystemet\Ledningssystemet\Traits\HasCustomProperties;
use Ledningssystemet\Ledningssystemet\Traits\HasMessages;
use Ledningssystemet\Ledningssystemet\Traits\HasNotifications;
use Ledningssystemet\Ledningssystemet\Traits\HasTags;

class ProjectRisk extends Model
{
    use DefersRelationAttributeSync, HasCustomProperties, HasMessages, HasNotifications, HasTags;

    protected $appends = [
        'access',
        'status',
        'updatedts',
        'risk_controls',
        'created_at_pretty',
        'name_pretty',
        'scenariodescription_pretty',
        'consequencedescription_pretty',
        'tags',
        'messagecount',
        'risk_level',
    ];

    protected $visible = [
        'access',
        'id',
        'name',
        'scenariodescription',
        'consequencedescription',
        'riskowner_id',
        'assessed_at',
        'created_at',
        'updated_at',
        'probability_id',
        'consequence_id',
        'status',
        'updatedts',
        'risk_controls',
        'note',
        'post_probability_id',
        'post_consequence_id',
        'project_id',
        'tags',
        'messagecount',
        'risk_level',
        'name_pretty',
        'scenariodescription_pretty',
        'consequencedescription_pretty',
    ];

    protected $fillable = [
        'name',
        'scenariodescription',
        'consequencedescription',
        'riskowner_id',
        'probability_id',
        'consequence_id',
        'risk_controls',
        'note',
        'post_probability_id',
        'post_consequence_id',
        'project_id',
    ];

    public $actions = ['approve', 'replace'];

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Project risks') : __('Project risk');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $risk): void {
            Validator::make($risk->toArray(), $risk->getValidationRules())->validate();

            $project = $risk->int_project;
            if (auth()->user() && $project && ! $project->canWrite(auth()->user())) {
                abort(403);
            }
            if ($project?->archived_at) {
                abort(400, __('You cannot modify any risks for an archived project'));
            }
            if ($risk->exists && $risk->isDirty('project_id')) {
                abort(422, __('The project of a risk cannot be changed'));
            }

            // Ensure that the risk owner is a member of the project
            if ($risk->riskowner_id && $project) {
                $isMember = $risk->riskowner_id == $project->responsible_user_id
                    || $project->int_users()->where('users.id', $risk->riskowner_id)->exists();
                if (! $isMember) {
                    abort(422, __('The assigned risk owner is not part of the project and will not be able to see or assess this risk'));
                }
            }
        });

        static::deleting(function (self $risk): void {
            if (auth()->user() && ! auth()->user()->hasAnyPermission(['riskadministrator.edit', 'superadmin.edit'])) {
                if ($risk->assessed_at) {
                    abort(400, __('You cannot delete an assessed risk'));
                }
                if ($risk->int_project?->archived_at) {
                    abort(400, __('You cannot delete a risk for an archived project'));
                }
            }
        });

        static::created(function (self $risk): void {
            ActivityLog::addMessage(
                __('The risk RISK-').$risk->id.' '.__('was added by').' '.(auth()->user()?->name ?? 'SYSTEM'),
                $risk->int_project,
            );
        });

        static::updated(function (self $risk): void {
            if ($risk->wasChanged()) {
                ActivityLog::addMessage(
                    __('The risk RISK-').$risk->id.' '.__('was updated by').' '.(auth()->user()?->name ?? 'SYSTEM'),
                    $risk->int_project,
                );
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

    public function getStatusAttribute(): array
    {
        if (! $this->riskowner_id) {
            return ['icon' => 'report', 'level' => 'danger', 'text' => __('This risk has not been assigned to a riskowner')];
        }

        $project = $this->int_project;
        $isMember = $project && (
            $this->riskowner_id == $project->responsible_user_id
            || $project->int_users()->where('users.id', $this->riskowner_id)->exists()
        );
        if (! $isMember) {
            return ['icon' => 'report', 'level' => 'danger', 'text' => __('The assigned risk owner is not part of the project and will not be able to see or assess this risk')];
        }

        if (! $this->assessed_at) {
            return [
                'icon' => 'report',
                'level' => request()->user()?->id === $this->riskowner_id ? 'danger' : 'warning',
                'text' => __('This risk is pending assessment'),
            ];
        }

        return ['icon' => 'check', 'level' => 'info', 'text' => ''];
    }

    public function getUpdatedtsAttribute(): ?string
    {
        return $this->updated_at ? date('Y-m-d H:i', strtotime($this->updated_at)) : null;
    }

    public function getCreatedAtPrettyAttribute(): ?string
    {
        return $this->created_at ? date('Y-m-d H:i', strtotime($this->created_at)) : null;
    }

    public function getNamePrettyAttribute(): string
    {
        return (string) $this->name;
    }

    public function getScenariodescriptionPrettyAttribute(): ?string
    {
        return $this->scenariodescription;
    }

    public function getConsequencedescriptionPrettyAttribute(): ?string
    {
        return $this->consequencedescription;
    }

    public function getRiskControlsAttribute(): array
    {
        return $this->int_controls
            ->map(fn (Control $control): array => ['id' => $control->id, 'name' => $control->name])
            ->all();
    }

    public function setRiskControlsAttribute($value): void
    {
        Validator::make(['risk_controls' => $value], [
            'risk_controls' => 'array',
            'risk_controls.*' => 'integer|distinct|exists:controls,id',
        ])->validate();
        $this->syncRelationAttribute('riskcontrols', function (self $model) use ($value): void {
            $model->int_controls()->sync($value);
            $model->unsetRelation('int_controls');
        });
    }

    public function getTagsAttribute()
    {
        return $this->tags()->get();
    }

    public function getMessagecountAttribute(): int
    {
        return $this->messages()->count();
    }

    public function getRiskLevelAttribute(): ?array
    {
        if (! $this->assessed_at) {
            return null;
        }

        $level = $this->int_risklevel();

        return $level ? [
            'id' => $level->id,
            'name' => $level->name,
            'color' => $level->color,
            'ordinal' => $level->ordinal,
        ] : null;
    }

    public function getValidationRules(): array
    {
        return [
            'name' => 'required',
            'riskowner_id' => 'nullable|exists:users,id',
            'probability_id' => 'nullable|exists:probability_levels,id',
            'consequence_id' => 'nullable|exists:consequence_levels,id',
            'post_probability_id' => 'nullable|exists:probability_levels,id',
            'post_consequence_id' => 'nullable|exists:consequence_levels,id',
            'note' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
        ];
    }

    public function int_project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function int_activities(): BelongsToMany
    {
        return $this->belongsToMany(ProjectActivity::class, 'project_risk_project_activities', 'project_risk_id', 'project_activity_id')->distinct();
    }

    public function int_controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'project_risk_controls', 'project_risk_id', 'control_id')->distinct();
    }

    public function int_probability(): BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'probability_id');
    }

    public function int_consequence(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'consequence_id');
    }

    public function int_riskowner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'riskowner_id');
    }

    public function int_risklevel()
    {
        return RiskLevel::getRisklevel($this->int_probability, $this->int_consequence);
    }

    public function approve(): void
    {
        if (! request()->user()->can('update', $this)) {
            abort(403);
        }
        if (auth()->id() != $this->riskowner_id) {
            abort(400, __('Only the risk owner can approve a risk'));
        }
        if ($this->assessed_at) {
            abort(400, __('The risk has already been approved'));
        }
        if (! $this->probability_id || ! $this->consequence_id) {
            abort(400, __('A risk may not be approved without being assessed'));
        }

        $riskLevel = RiskLevel::getRisklevel(
            ProbabilityLevel::findOrFail($this->probability_id),
            ConsequenceLevel::findOrFail($this->consequence_id),
        );
        if (! $riskLevel) {
            abort(400, __('Could not derive risk level based on the current assessment'));
        }
        if (! auth()->user()->risklevel() || auth()->user()->risklevel()->ordinal < $riskLevel->ordinal) {
            abort(400, __('You are not authorized to approve risks on this level, but need to escalate the risk to higher management'));
        }

        $this->assessed_at = now();
        $this->save();
    }

    public function replace($reason = null): void
    {
        if (request()->user() && ! request()->user()->can('update', $this)) {
            abort(403);
        }
        if (! $this->assessed_at) {
            abort(400, __('The risk is not assessed and can therefore not be replaced'));
        }
        $this->assessed_at = null;
        $this->save();
        ActivityLog::addMessage(
            __('Reassess risk').($reason ? ': '.$reason : ''),
            $this,
        );
    }
}
