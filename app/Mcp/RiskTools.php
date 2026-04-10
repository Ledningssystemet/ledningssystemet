<?php

namespace App\Mcp;

use App\Models\Risk;
use App\Models\RiskProject;
use PhpMcp\Server\Attributes\McpTool;

class RiskTools
{
    /**
     * List risks with optional search and filters.
     *
     * @param  string  $search  Optional search term to filter by name or scenario description.
     * @param  int|null  $risk_project_id  Optional risk project ID to filter by.
     * @param  int|null  $department_id  Optional department ID to filter by.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of risks with probability, consequence and linked risk project.
     */
    #[McpTool(name: 'list_risks')]
    public function listRisks(
        string $search = '',
        ?int $risk_project_id = null,
        ?int $department_id = null,
        int $limit = 50
    ): array {
        $query = Risk::with([
            'int_department:id,name',
            'int_probability:id,name,value',
            'int_consequence:id,name,value',
            'int_risk_project:id,name',
            'int_riskowner:id,name',
        ])->whereNull('replacedby_id'); // Only show current (non-replaced) risks

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('scenariodescription', 'like', "%{$search}%")
                    ->orWhere('consequencedescription', 'like', "%{$search}%");
            });
        }

        if ($risk_project_id !== null) {
            $query->where('risk_project_id', $risk_project_id);
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
            'risk_project'            => $r->int_risk_project?->name,
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
            'int_risk_project:id,name',
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
            'risk_project'            => $risk->int_risk_project?->name,
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
     * List all risk projects.
     *
     * @param  string  $search  Optional search term to filter by name.
     * @return array List of risk projects with id and name.
     */
    #[McpTool(name: 'list_risk_projects')]
    public function listRiskProjects(string $search = ''): array
    {
        $query = RiskProject::select(['id', 'name', 'description']);

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get()->toArray();
    }
}

