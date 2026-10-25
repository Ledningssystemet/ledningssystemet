<?php

namespace Ledningssystemet\Ledningssystemet\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeeting;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingAgendaTemplate;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingAgendaTemplateItem;

class ProjectMeetingAgenda
{
    public static function forProject(Project $project, string $date, ?int $templateId = null): array
    {
        $templates = ProjectMeetingAgendaTemplate::orderBy('name')->get();
        if ($templateId !== null && ! $templates->contains('id', $templateId)) {
            throw ValidationException::withMessages(['project_meeting_agenda_template_id' => __('Select a valid meeting agenda template')]);
        }
        $sections = [];
        if ($templateId !== null) {
            foreach (ProjectMeetingAgendaTemplateItem::where('project_meeting_agenda_template_id', $templateId)->orderBy('ordinal')->orderBy('id')->get() as $item) {
                $sections[] = self::section('template-'.$item->id, $item->name, 'template', $item->description ?? '');
            }
        }
        $previous = $templateId !== null ? ProjectMeeting::where('project_id', $project->id)
            ->where('project_meeting_agenda_template_id', $templateId)
            ->where('date', '<=', $date)->orderByDesc('date')->orderByDesc('id')->first() : null;
        $followUp = null;
        if ($previous) {
            $ids = array_unique(array_merge(...array_map(fn (array $section) => $section['activity_ids'], $previous->agenda)));
            $openIds = DB::table('project_activities')->where('project_id', $project->id)
                ->whereIn('id', $ids)->whereNull('completed_at')->pluck('id')->all();
            $followUp = self::section('followup-'.$previous->id, __('Follow-up from previous meeting'), 'followup');
            $followUp['previous_meeting_id'] = $previous->id;
            $followUp['activity_ids'] = $openIds;
        }

        return [
            'templates' => $templates->map(fn (ProjectMeetingAgendaTemplate $template) => ['id' => $template->id, 'name' => $template->name])->all(),
            'sections' => $sections,
            'previous_meeting' => $previous ? ['id' => $previous->id, 'name' => $previous->name, 'date' => $previous->date] : null,
            'follow_up_section' => $followUp,
        ];
    }

    private static function section(string $id, string $title, string $source, string $description = ''): array
    {
        return [
            'id' => $id, 'title' => $title, 'source' => $source, 'description' => $description,
            'document' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            'activity_ids' => [],
        ];
    }

    public static function protect(array $agenda, array $required): void
    {
        $byId = array_column($agenda, null, 'id');
        $requiredIds = array_column($required, 'id');
        foreach ($required as $section) {
            $actual = $byId[$section['id']] ?? null;
            foreach (['id', 'title', 'source', 'description', 'previous_meeting_id'] as $key) {
                if ($actual === null || ($actual[$key] ?? null) !== ($section[$key] ?? null)) {
                    throw ValidationException::withMessages(['agenda' => __('Mandatory agenda items cannot be removed or renamed')]);
                }
            }
        }
        foreach ($agenda as $section) {
            if (! in_array($section['id'], $requiredIds, true) && (($section['source'] ?? 'custom') !== 'custom' || isset($section['previous_meeting_id']))) {
                throw ValidationException::withMessages(['agenda' => __('Invalid mandatory agenda item')]);
            }
        }
    }
}
