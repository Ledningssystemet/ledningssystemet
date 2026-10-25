<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class ProjectTypeMeetingAgendaItem extends Model
{
    protected $fillable = ['agenda_template_id', 'name', 'description', 'ordinal'];

    protected $visible = ['id', 'agenda_template_id', 'name', 'description', 'ordinal', 'access'];

    protected $attributes = ['description' => null, 'ordinal' => 0];

    protected $appends = ['access'];

    protected $casts = ['ordinal' => 'integer'];

    protected static function booted(): void
    {
        static::saving(fn (self $item) => Validator::make($item->attributesToArray(), $item->getValidationRules())->validate());
    }

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Standard agenda items') : __('Standard agenda item');
    }

    public function getValidationRules(): array
    {
        return [
            'agenda_template_id' => 'required|integer|exists:project_meeting_agenda_templates,id',
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
        return self::where('agenda_template_id', request()->input('agenda_template_id'))
            ->orderBy('ordinal')->orderBy('id')->paginate(request()->query('per_page'));
    }

    public function int_template(): BelongsTo
    {
        return $this->belongsTo(ProjectMeetingAgendaTemplate::class, 'agenda_template_id');
    }
}
