<?php

namespace App\Mcp;

use App\Models\ComplianceEvaluation;
use App\Models\Requirement;
use App\Models\RequirementSource;
use PhpMcp\Server\Attributes\McpTool;

class ComplianceTools
{
    /**
     * List compliance evaluations with optional search.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  bool  $include_archived  Whether to include archived evaluations (default false).
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of compliance evaluations with status.
     */
    #[McpTool(name: 'list_compliance_evaluations')]
    public function listComplianceEvaluations(
        string $search = '',
        bool $include_archived = false,
        int $limit = 50
    ): array {
        $query = ComplianceEvaluation::withCount('int_compliance_evaluation_requirement')
            ->select(['id', 'name', 'description', 'startdate', 'finished', 'archived', 'summary']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! $include_archived) {
            $query->whereNull('archived');
        }

        return $query->latest('startdate')->limit($limit)->get()->map(fn (ComplianceEvaluation $e) => [
            'id'                  => $e->id,
            'name'                => $e->name,
            'description'         => $e->description,
            'start_date'          => $e->startdate?->toDateString(),
            'finished_at'         => $e->finished?->toDateString(),
            'archived_at'         => $e->archived?->toDateString(),
            'summary'             => $e->summary,
            'requirements_count'  => $e->int_compliance_evaluation_requirement_count,
        ])->toArray();
    }

    /**
     * Get detailed information about a specific compliance evaluation including requirements and findings.
     *
     * @param  int  $id  The ID of the compliance evaluation.
     * @return array Compliance evaluation details with requirements and findings.
     */
    #[McpTool(name: 'get_compliance_evaluation')]
    public function getComplianceEvaluation(int $id): array
    {
        $evaluation = ComplianceEvaluation::with([
            'int_compliance_evaluation_requirement.int_requirement:id,name',
            'int_compliance_evaluation_requirement.int_compliance_evaluation_requirement_findings:id,compliance_evaluation_requirement_id,name,description,isnc',
        ])->findOrFail($id);

        return [
            'id'           => $evaluation->id,
            'name'         => $evaluation->name,
            'description'  => $evaluation->description,
            'participants' => $evaluation->participants,
            'start_date'   => $evaluation->startdate?->toDateString(),
            'finished_at'  => $evaluation->finished?->toDateString(),
            'archived_at'  => $evaluation->archived?->toDateString(),
            'summary'      => $evaluation->summary,
            'requirements' => $evaluation->int_compliance_evaluation_requirement->map(fn ($cer) => [
                'id'          => $cer->id,
                'requirement' => $cer->int_requirement?->name,
                'evaluation'  => $cer->evaluation ?? null,
                'findings'    => $cer->int_compliance_evaluation_requirement_findings->map(fn ($f) => [
                    'id'              => $f->id,
                    'name'            => $f->name,
                    'description'     => $f->description,
                    'non_conformance' => $f->isnc,
                ])->toArray(),
            ])->toArray(),
        ];
    }

    /**
     * List requirements with optional search and source filter.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  int|null  $requirement_source_id  Optional requirement source ID to filter by.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of requirements.
     */
    #[McpTool(name: 'list_requirements')]
    public function listRequirements(
        string $search = '',
        ?int $requirement_source_id = null,
        int $limit = 50
    ): array {
        $query = Requirement::with(['int_requirement_source:id,name'])
            ->select(['id', 'name', 'description', 'requirement_source_id']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($requirement_source_id !== null) {
            $query->where('requirement_source_id', $requirement_source_id);
        }

        return $query->limit($limit)->get()->map(fn (Requirement $r) => [
            'id'                 => $r->id,
            'name'               => $r->name,
            'description'        => $r->description,
            'requirement_source' => $r->int_requirement_source?->name,
        ])->toArray();
    }

    /**
     * List requirement sources (laws, standards, frameworks, etc.).
     *
     * @return array List of all requirement sources.
     */
    #[McpTool(name: 'list_requirement_sources')]
    public function listRequirementSources(): array
    {
        return RequirementSource::select(['id', 'name', 'description'])
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}

