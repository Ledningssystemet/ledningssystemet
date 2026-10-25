<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectMeetingAgendaTemplateItem extends Model
{
    protected $fillable = ['project_meeting_agenda_template_id', 'name', 'description', 'ordinal'];

    protected $visible = ['id', 'project_meeting_agenda_template_id', 'name', 'description', 'ordinal', 'access'];

    protected $attributes = ['description' => null, 'ordinal' => 0];

    protected $appends = ['access'];

    protected $casts = ['ordinal' => 'integer'];

    public $actions = ['reorder'];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->ordinal = (int) self::where('project_meeting_agenda_template_id', $item->project_meeting_agenda_template_id)->max('ordinal') + 2;
        });

        static::saving(fn (self $item) => Validator::make($item->attributesToArray(), $item->getValidationRules())->validate());
    }

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Meeting agenda items') : __('Meeting agenda item');
    }

    public function getValidationRules(): array
    {
        return [
            'project_meeting_agenda_template_id' => 'required|integer|exists:project_meeting_agenda_templates,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'ordinal' => 'required|integer|min:0|max:100000',
        ];
    }

    public function getAccessAttribute(): ?array
    {
        $user = auth()->user();

        return $user ? ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)] : null;
    }

    public static function index(User $user)
    {
        return self::where('project_meeting_agenda_template_id', request()->input('project_meeting_agenda_template_id'))
            ->orderBy('ordinal')->orderBy('id')->paginate(request()->query('per_page'));
    }

    public function int_template(): BelongsTo
    {
        return $this->belongsTo(ProjectMeetingAgendaTemplate::class, 'project_meeting_agenda_template_id');
    }

    public function reorder(): array
    {
        abort_unless(request()->user()?->can('update', $this), 403);

        return DB::transaction(function (): array {
            $items = self::where('project_meeting_agenda_template_id', $this->project_meeting_agenda_template_id)
                ->orderBy('ordinal')->orderBy('id')->lockForUpdate()->get();
            $afterId = request()->input('after');
            Validator::make(['after' => $afterId], ['after' => 'nullable|integer|min:1'])->validate();
            $after = $afterId === null
                ? null
                : $items->firstWhere('id', (int) $afterId);
            abort_if($afterId !== null && (! $after || $after->is($this)), 422);

            $ordered = $items->reject(fn (self $item): bool => $item->is($this))->values();
            $position = $after ? $ordered->search(fn (self $item): bool => $item->is($after)) + 1 : 0;
            $ordered->splice($position, 0, [$this]);

            foreach ($ordered as $index => $item) {
                $item->ordinal = $index * 2;
                $item->save();
            }

            return [];
        });
    }
}
