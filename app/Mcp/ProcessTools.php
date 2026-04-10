<?php

namespace App\Mcp;

use App\Models\Process;
use App\Models\ProcessActivity;
use PhpMcp\Server\Attributes\McpTool;

class ProcessTools
{
    /**
     * List processes with optional search and department filter.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  int|null  $department_id  Optional department ID to filter by.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of processes with id, name, description, department and responsible user.
     */
    #[McpTool(name: 'list_processes')]
    public function listProcesses(string $search = '', ?int $department_id = null, int $limit = 50): array
    {
        $query = Process::with(['int_department:id,name', 'int_responsible_user:id,name'])
            ->select(['id', 'name', 'description', 'department_id', 'responsible_user_id', 'isstartprocess']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($department_id !== null) {
            $query->where('department_id', $department_id);
        }

        return $query->limit($limit)->get()->map(fn (Process $p) => [
            'id'               => $p->id,
            'name'             => $p->name,
            'description'      => $p->description,
            'is_start_process' => $p->isstartprocess,
            'department'       => $p->int_department?->name,
            'department_id'    => $p->department_id,
            'responsible_user' => $p->int_responsible_user?->name,
        ])->toArray();
    }

    /**
     * Get detailed information about a specific process including its activities.
     *
     * @param  int  $id  The ID of the process.
     * @return array Process details with activities list.
     */
    #[McpTool(name: 'get_process')]
    public function getProcess(int $id): array
    {
        $process = Process::with([
            'int_department:id,name',
            'int_responsible_user:id,name',
            'int_process_activities:id,process_id,name,description',
        ])->findOrFail($id);

        return [
            'id'               => $process->id,
            'name'             => $process->name,
            'description'      => $process->description,
            'is_start_process' => $process->isstartprocess,
            'department'       => $process->int_department?->name,
            'responsible_user' => $process->int_responsible_user?->name,
            'activities'       => $process->int_process_activities->map(fn (ProcessActivity $a) => [
                'id'          => $a->id,
                'name'        => $a->name,
                'description' => $a->description,
            ])->toArray(),
        ];
    }
}

