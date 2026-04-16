<?php

namespace App\Mcp;

use App\Models\Risk;
use App\Models\Project;
use PhpMcp\Server\Attributes\McpTool;

class RiskTools
{
    /**
     * List risks with optional search and filters.
     *
     * @param  string  $search  Optional search term to filter by name or scenario description.
     * @param  int|null  $project_id  Optional risk project ID to filter by.
     * @param  int|null  $department_id  Optional department ID to filter by.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of risks with probability, consequence and linked risk project.
     */
    #[McpTool(name: 'list_risks')]
    public function listRisks(
        string $search = '',
        ?int $project_id = null,
        ?int $department_id = null,
        int $limit = 50
    ): array {
        $query = Risk::with([
            'int_department:id,name',
            'int_probability:id,name,value',
            'int_consequence:id,name,value',
            'int_project:id,name',
            'int_riskowner:id,name',
        ])->whereNull('replacedby_id'); // Only show current (non-replaced) risks

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('scenariodescription', 'like', "%{$search}%")
                    ->orWhere('consequencedescription', 'like', "%{$search}%");
            });
        }

        if ($project_id !== null) {
            $query->where('project_id', $project_id);
        }

        if ($department_id !== null) {
            $query->where('department_id', $department_id);
        }

        return $query->limit($limit)->get()->map(fn (Risk $r) => [
            'id'                      => $r->id,
            'name'                    => $r->name,
            'scenario_description'    => $r->scenariodescription,
            'consequence_description' => $r->consequencedescription,
            'department'              => $r->int_department?->name,
            'project'            => $r->int_project?->name,
            'risk_owner'              => $r->int_riskowner?->name,
            'probability'             => $r->int_probability?->name,
            'consequence'             => $r->int_consequence?->name,
            'assessed_at'             => $r->assessed_at?->toDateString(),
        ])->toArray();
    }

    /**
     * Get detailed information about a specific risk including linked controls.
     *
     * @param  int  $id  The ID of the risk.
     * @return array Risk details with controls and assessment data.
     */
    #[McpTool(name: 'get_risk')]
    public function getRisk(int $id): array
    {
        $risk = Risk::with([
            'int_department:id,name',
            'int_probability:id,name,value',
            'int_consequence:id,name,value',
            'int_post_probability:id,name,value',
            'int_post_consequence:id,name,value',
            'int_project:id,name',
            'int_riskowner:id,name',
            'int_controls:id,name,description',
        ])->findOrFail($id);

        return [
            'id'                      => $risk->id,
            'name'                    => $risk->name,
            'scenario_description'    => $risk->scenariodescription,
            'consequence_description' => $risk->consequencedescription,
            'assessment_comment'      => $risk->assessmentcomment,
            'department'              => $risk->int_department?->name,
            'project'                 => $risk->int_project?->name,
            'risk_owner'              => $risk->int_riskowner?->name,
            'probability'             => $risk->int_probability?->name,
            'probability_value'       => $risk->int_probability?->value,
            'consequence'             => $risk->int_consequence?->name,
            'consequence_value'       => $risk->int_consequence?->value,
            'post_probability'        => $risk->int_post_probability?->name,
            'post_consequence'        => $risk->int_post_consequence?->name,
            'assessed_at'             => $risk->assessed_at?->toDateString(),
            'controls'                => $risk->int_controls->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
            ])->toArray(),
        ];
    }

    /**
     * List all projects.
     *
     * @param  string  $search  Optional search term to filter by name.
     * @return array List of projects with id and name.
     */
    #[McpTool(name: 'list_projects')]
    public function listProjects(string $search = ''): array
    {
        $query = Project::select(['id', 'name', 'description']);

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get()->toArray();
    }
}

