<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\QueryBuilder\AllowedFilter;

class ProjectMeetingMention extends Model
{
    protected $fillable = [];

    protected $visible = ['id', 'project_meeting_id', 'user_id', 'agenda_item_id', 'excerpt', 'acknowledged_at', 'created_at', 'project_id', 'project_name', 'meeting_name'];

    protected $appends = ['project_id', 'project_name', 'meeting_name'];

    protected $with = ['int_meeting.int_project'];

    public $actions = ['acknowledge'];

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Mentions') : __('Mention');
    }

    public function acknowledge(): self
    {
        abort_unless(auth()->id() === $this->user_id && auth()->user()->can('view', $this->int_meeting), 403);
        // A conditional update keeps repeated acknowledgements idempotent.
        self::whereKey($this->id)->whereNull('acknowledged_at')->update(['acknowledged_at' => now(), 'updated_at' => now()]);

        return $this->refresh();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)
            ->whereHas('int_meeting', fn (Builder $meeting) => $meeting->visibleTo($user));
    }

    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::callback('project_id', fn (Builder $query, $id) => $query->whereHas('int_meeting', fn (Builder $meeting) => $meeting->where('project_id', $id))),
        ];
    }

    public static function getAllowedIncludes(): array
    {
        return [];
    }

    public function getAuthorizationFields(): array
    {
        return ['id', 'user_id', 'project_meeting_id'];
    }

    public function getProjectIdAttribute(): int
    {
        return $this->int_meeting->project_id;
    }

    public function getProjectNameAttribute(): string
    {
        return $this->int_meeting->int_project->name;
    }

    public function getMeetingNameAttribute(): string
    {
        return $this->int_meeting->name;
    }

    public function int_meeting(): BelongsTo
    {
        return $this->belongsTo(ProjectMeeting::class, 'project_meeting_id');
    }
}
