<?php

namespace App\Mcp;

use App\Models\Incident;
use PhpMcp\Server\Attributes\McpTool;

class IncidentTools
{
    /**
     * List incidents with optional search.
     *
     * @param  string  $search  Optional search term to filter by name or event description.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of incidents with dates and responsible user.
     */
    #[McpTool(name: 'list_incidents')]
    public function listIncidents(string $search = '', int $limit = 50): array
    {
        $query = Incident::with(['int_responsible_user:id,name'])
            ->withCount('int_incident_logs')
            ->select(['id', 'name', 'started_at', 'finished_at', 'eventdescription', 'responsible_user_id']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('eventdescription', 'like', "%{$search}%");
            });
        }

        return $query->latest('started_at')->limit($limit)->get()->map(fn (Incident $i) => [
            'id'               => $i->id,
            'name'             => $i->name,
            'started_at'       => $i->started_at?->toDateTimeString(),
            'finished_at'      => $i->finished_at?->toDateTimeString(),
            'responsible_user' => $i->int_responsible_user?->name,
            'log_entries'      => $i->int_incident_logs_count,
        ])->toArray();
    }

    /**
     * Get detailed information about a specific incident including log entries.
     *
     * @param  int  $id  The ID of the incident.
     * @return array Incident details with all log entries.
     */
    #[McpTool(name: 'get_incident')]
    public function getIncident(int $id): array
    {
        $incident = Incident::with([
            'int_responsible_user:id,name',
            'int_incident_logs:id,incident_id,description,created_at',
        ])->findOrFail($id);

        return [
            'id'               => $incident->id,
            'name'             => $incident->name,
            'started_at'       => $incident->started_at?->toDateTimeString(),
            'finished_at'      => $incident->finished_at?->toDateTimeString(),
            'event_description' => $incident->eventdescription,
            'participants'     => $incident->participants,
            'retrospective'    => $incident->retrospective,
            'responsible_user' => $incident->int_responsible_user?->name,
            'log_entries'      => $incident->int_incident_logs->map(fn ($log) => [
                'id'          => $log->id,
                'description' => $log->description,
                'created_at'  => $log->created_at?->toDateTimeString(),
            ])->toArray(),
        ];
    }
}

