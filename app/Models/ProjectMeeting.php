<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Ledningssystemet\Ledningssystemet\Support\MeetingDocument;
use Ledningssystemet\Ledningssystemet\Support\ProjectMeetingAgenda;

class ProjectMeeting extends Model
{
    protected ?bool $includeFollowUp = null;

    protected ?int $followUpMeetingId = null;

    protected $attributes = ['agenda' => '[]', 'revision' => 0, 'location' => null, 'completed_at' => null, 'project_meeting_agenda_template_id' => null, 'previous_meeting_id' => null];

    protected $fillable = ['project_id', 'name', 'date', 'location', 'agenda', 'revision', 'completed_at', 'project_meeting_agenda_template_id', 'include_follow_up', 'follow_up_meeting_id'];

    protected $visible = ['id', 'project_id', 'name', 'date', 'location', 'agenda', 'revision', 'completed_at', 'access', 'created_at', 'updated_at', 'project_meeting_agenda_template_id', 'previous_meeting_id'];

    protected $casts = ['agenda' => 'array', 'revision' => 'integer'];

    protected $appends = ['access'];

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Project meetings') : __('Project meeting');
    }

    public function getValidationRules(): array
    {
        return [
            'project_id' => 'required|integer|exists:projects,id',
            'name' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'location' => 'nullable|string|max:255',
            'agenda' => 'present|array',
            'revision' => 'required|integer|min:0',
            'completed_at' => 'nullable|date',
            'project_meeting_agenda_template_id' => 'nullable|integer|exists:project_meeting_agenda_templates,id',
        ];
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $project = Project::whereKey($this->getOriginal('project_id') ?? $this->project_id)->lockForUpdate()->firstOrFail();
            abort_unless(auth()->user() && $project->canWrite(auth()->user()), 403);
            abort_if($project->archived_at, 422, __('You cannot modify meetings for an archived project'));
            abort_if($this->exists && $this->isDirty('project_id'), 422, __('The project of a meeting cannot be changed'));
            if ($this->exists) {
                abort_if($this->isDirty('project_meeting_agenda_template_id') || $this->includeFollowUp !== null || $this->followUpMeetingId !== null, 422, __('The meeting agenda source cannot be changed'));
                if (request()->isMethod('PATCH')) {
                    Validator::make(request()->all(), ['revision' => 'required|integer|min:0'])->validate();
                }
                $current = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
                abort_if($current->completed_at !== null, 422, __('The meeting is completed and locked'));
                abort_unless($this->revision === $current->revision, 409, __('The meeting has changed. Reload before saving.'));
            }
            Validator::make($this->attributesToArray(), $this->getValidationRules())->validate();
            $mentions = MeetingDocument::mentions($this->agenda);
            $required = $this->exists
                ? array_values(array_filter($current->agenda, fn (array $section) => in_array($section['source'] ?? 'custom', ['template', 'followup'], true)))
                : [];
            if (! $this->exists && $this->project_meeting_agenda_template_id !== null) {
                $template = ProjectMeetingAgenda::forProject($project, $this->date, (int) $this->project_meeting_agenda_template_id);
                $required = $template['sections'];
                $previousId = $template['previous_meeting']['id'] ?? null;
                abort_unless($this->followUpMeetingId === $previousId, 409, __('The previous meeting has changed. Reload the agenda.'));
                if ($previousId !== null) {
                    abort_if($this->includeFollowUp === null, 422, __('Confirm whether to include follow-up before creating the meeting'));
                    if ($this->includeFollowUp) {
                        array_unshift($required, $template['follow_up_section']);
                        $this->previous_meeting_id = $previousId;
                        $agenda = $this->agenda;
                        foreach ($agenda as &$section) {
                            if ($section['id'] === $template['follow_up_section']['id']) {
                                $section['activity_ids'] = array_values(array_unique([...$section['activity_ids'], ...$template['follow_up_section']['activity_ids']]));
                            }
                        }
                        unset($section);
                        $this->agenda = $agenda;
                    }
                }
            }
            ProjectMeetingAgenda::protect($this->agenda, $required);
            $members = $project->int_users()->pluck('users.id')->all();
            $members[] = $project->responsible_user_id;
            Validator::make(['mentions' => array_column($mentions, 'user_id')], [
                'mentions.*' => [Rule::in(array_filter($members))],
            ])->validate();
            foreach ($this->agenda as $section) {
                Validator::make($section, [
                    'activity_ids.*' => [Rule::exists('project_activities', 'id')->where('project_id', $project->id)],
                ])->validate();
            }

            // Completion time is authoritative; clients only request the transition.
            if ($this->completed_at !== null) {
                $this->completed_at = now();
            }
            $this->revision++;
            $saved = parent::save($options);
            $keep = [];
            foreach ($mentions as $mention) {
                $key = [
                    'agenda_item_id' => $mention['agenda_item_id'], 'user_id' => $mention['user_id'],
                ];
                $row = $this->int_mentions()->where($key)->first() ?? new ProjectMeetingMention;
                $row->forceFill([...$key, 'project_meeting_id' => $this->id]);
                $row->excerpt = $mention['excerpt'];
                if ($row->exists && $row->isDirty('excerpt')) {
                    $row->acknowledged_at = null;
                }
                $row->save();
                $keep[] = $row->id;
            }
            $this->int_mentions()->whereNotIn('id', $keep)->delete();
            $this->includeFollowUp = null;
            $this->followUpMeetingId = null;

            return $saved;
        });
    }

    public function setIncludeFollowUpAttribute($value): void
    {
        Validator::make(['value' => $value], ['value' => 'required|boolean'])->validate();
        $this->includeFollowUp = (bool) $value;
    }

    public function setFollowUpMeetingIdAttribute($value): void
    {
        Validator::make(['value' => $value], ['value' => 'nullable|integer|min:1'])->validate();
        $this->followUpMeetingId = $value === null ? null : (int) $value;
    }

    public function delete()
    {
        return DB::transaction(function () {
            $project = Project::whereKey($this->project_id)->lockForUpdate()->firstOrFail();
            abort_unless(auth()->user() && $project->canWrite(auth()->user()), 403);
            $current = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            abort_if($current->completed_at || $project->archived_at, 422, __('The meeting is completed and locked'));

            return parent::delete();
        });
    }

    public function getAccessAttribute(): ?array
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        $write = ! $this->completed_at && $this->int_project && ! $this->int_project->archived_at && $this->int_project->canWrite($user);

        return ['read' => $user->can('view', $this), 'update' => (bool) $write, 'delete' => (bool) $write];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('int_project', fn (Builder $project) => $project->visibleTo($user));
    }

    public static function getAllowedIncludes(): array
    {
        return [];
    }

    public function getAuthorizationFields(): array
    {
        return ['id', 'project_id', 'completed_at'];
    }

    public function int_project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function int_mentions(): HasMany
    {
        return $this->hasMany(ProjectMeetingMention::class, 'project_meeting_id');
    }
}
