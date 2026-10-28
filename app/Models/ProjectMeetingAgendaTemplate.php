<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

class ProjectMeetingAgendaTemplate extends Model
{
    protected $fillable = ['name'];

    protected $visible = ['id', 'name', 'access'];

    protected $appends = ['access'];

    protected static function booted(): void
    {
        static::saving(fn (self $template) => Validator::make($template->attributesToArray(), $template->getValidationRules())->validate());
    }

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Meeting agendas') : __('Meeting agenda');
    }

    public function getValidationRules(): array
    {
        return ['name' => 'required|string|max:255'];
    }

    public function getAccessAttribute(): ?array
    {
        $user = auth()->user();

        return $user ? ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)] : null;
    }

    public static function index(User $user)
    {
        return self::orderBy('name')->paginate(request()->query('per_page'));
    }

    public function int_items(): HasMany
    {
        return $this->hasMany(ProjectMeetingAgendaTemplateItem::class, 'project_meeting_agenda_template_id')->orderBy('ordinal')->orderBy('id');
    }

}
