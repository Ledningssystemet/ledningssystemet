<?php

namespace App\Mcp;

use App\Models\Control;
use PhpMcp\Server\Attributes\McpTool;

class ControlTools
{
    /**
     * List security controls with optional search.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of controls with status and linked risks count.
     */
    #[McpTool(name: 'list_controls')]
    public function listControls(string $search = '', int $limit = 50): array
    {
        $query = Control::withCount('int_risks')
            ->with(['int_responsible_user:id,name'])
            ->select(['id', 'name', 'description', 'responsible_user_id', 'statusdescription', 'not_applicable_at', 'reviewed_at']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('statusdescription', 'like', "%{$search}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (Control $c) => [
            'id'                 => $c->id,
            'name'               => $c->name,
            'description'        => $c->description,
            'status_description' => $c->statusdescription,
            'responsible_user'   => $c->int_responsible_user?->name,
            'not_applicable_at'  => $c->not_applicable_at?->toDateString(),
            'reviewed_at'        => $c->reviewed_at?->toDateString(),
            'linked_risks_count' => $c->int_risks_count,
        ])->toArray();
    }

    /**
     * Get detailed information about a specific control including linked risks and requirements.
     *
     * @param  int  $id  The ID of the control.
     * @return array Control details with linked risks and requirements.
     */
    #[McpTool(name: 'get_control')]
    public function getControl(int $id): array
    {
        $control = Control::with([
            'int_responsible_user:id,name',
            'int_risks:id,name',
            'int_requirements:id,name',
        ])->findOrFail($id);

        return [
            'id'                 => $control->id,
            'name'               => $control->name,
            'description'        => $control->description,
            'status_description' => $control->statusdescription,
            'responsible_user'   => $control->int_responsible_user?->name,
            'not_applicable_at'  => $control->not_applicable_at?->toDateString(),
            'reviewed_at'        => $control->reviewed_at?->toDateString(),
            'risks'              => $control->int_risks->map(fn ($r) => [
                'id'   => $r->id,
                'name' => $r->name,
            ])->toArray(),
            'requirements'       => $control->int_requirements->map(fn ($req) => [
                'id'   => $req->id,
                'name' => $req->name,
            ])->toArray(),
        ];
    }
}

