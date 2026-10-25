<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

class ProjectMeetingAgendaTemplate extends Model
{
    protected $fillable = ['project_type_id', 'name'];

    protected $visible = ['id', 'project_type_id', 'name', 'access'];

    protected $appends = ['access'];

    protected static function booted(): void
    {
        static::saving(fn (self $template) => Validator::make($template->attributesToArray(), $template->getValidationRules())->validate());
    }

    public static function getPrettyName($plural = false)
    {
        return $plural ? __('Standard agendas') : __('Standard agenda');
    }

    public function getValidationRules(): array
    {
        return ['project_type_id' => 'required|integer|exists:project_types,id', 'name' => 'required|string|max:255'];
    }

    public function getAccessAttribute(): ?array
    {
        $user = auth()->user();

        return $user ? ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)] : null;
    }

    public static function index(User $user)
    {
        return self::where('project_type_id', request()->input('project_type_id'))->orderBy('name')->paginate(request()->query('per_page'));
    }

    public function int_items(): HasMany
    {
        return $this->hasMany(ProjectTypeMeetingAgendaItem::class, 'agenda_template_id')->orderBy('ordinal')->orderBy('id');
    }

    public function int_project_type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }
}
