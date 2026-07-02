<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplianceEvaluation;
use App\Models\ComplianceEvaluationRequirement;
use App\Models\ComplianceEvaluationRequirementSource;
use App\Models\Requirement;
use App\Models\RequirementSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ComplianceEvaluationController extends Controller
{
    /**
     * Get evaluation with statistics and requirement sources.
     */
    public function show(Request $request, ComplianceEvaluation $evaluation): JsonResponse
    {
        Gate::authorize('view', $evaluation);

        $evaluation->load([
            'int_compliance_evaluation_requirement_source.int_requirement_source',
        ]);

        $stats = $this->computeStatistics($evaluation->id);
        $evaluation->setAttribute('statistics', $stats);

        return response()->json($evaluation);
    }

    /**
     * Generate checklist rows from selected requirement source IDs.
     */
    public function generateChecklist(Request $request, ComplianceEvaluation $evaluation): JsonResponse
    {
        Gate::authorize('update', $evaluation);

        $data = $request->validate([
            'reqsources' => ['required', 'array'],
            'reqsources.*' => ['integer', 'exists:requirement_sources,id'],
        ]);

        DB::transaction(function () use ($evaluation, $data): void {
            $reqSourceIds = $data['reqsources'];

            // Sync requirement sources
            $existingCersMap = ComplianceEvaluationRequirementSource::where('compliance_evaluation_id', $evaluation->id)
                ->pluck('id', 'requirement_source_id')
                ->all();

            $newSourceIds = array_diff($reqSourceIds, array_keys($existingCersMap));
            $removedSourceIds = array_diff(array_keys($existingCersMap), $reqSourceIds);

            // Remove requirement source entries and their requirements for removed sources
            foreach ($removedSourceIds as $srcId) {
                $cersId = $existingCersMap[$srcId];
                ComplianceEvaluationRequirement::where('cers_id', $cersId)->delete();
                ComplianceEvaluationRequirementSource::where('id', $cersId)->delete();
            }

            // Add new requirement sources and generate requirements
            foreach ($newSourceIds as $srcId) {
                $cers = ComplianceEvaluationRequirementSource::create([
                    'compliance_evaluation_id' => $evaluation->id,
                    'requirement_source_id' => $srcId,
                ]);

                $requirements = Requirement::where('requirement_source_id', $srcId)
                    ->orderBy('ordinal')
                    ->get();

                foreach ($requirements as $req) {
                    ComplianceEvaluationRequirement::create([
                        'compliance_evaluation_id' => $evaluation->id,
                        'requirement_id' => $req->id,
                        'cers_id' => $cers->id,
                        'name' => $req->name,
                        'reference' => $req->reference,
                        'description' => $req->description,
                        'governance' => $req->governance,
                        'note' => null,
                        'evaluated' => false,
                        'applicable' => true,
                    ]);
                }
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Mark evaluation as finished.
     */
    public function finish(Request $request, ComplianceEvaluation $evaluation): JsonResponse
    {
        Gate::authorize('update', $evaluation);

        $evaluation->update(['finished' => now()]);

        return response()->json($evaluation->fresh());
    }

    /**
     * Re-open a finished evaluation.
     */
    public function reopen(Request $request, ComplianceEvaluation $evaluation): JsonResponse
    {
        Gate::authorize('update', $evaluation);

        $evaluation->update(['finished' => null]);

        return response()->json($evaluation->fresh());
    }

    /**
     * Archive a finished evaluation.
     */
    public function archive(Request $request, ComplianceEvaluation $evaluation): JsonResponse
    {
        Gate::authorize('update', $evaluation);

        $evaluation->update(['archived' => now()]);

        return response()->json($evaluation->fresh());
    }

    /**
     * Get requirement sources for an evaluation, with their requirements.
     */
    public function requirementSources(Request $request, ComplianceEvaluation $evaluation): JsonResponse
    {
        Gate::authorize('view', $evaluation);

        $sources = ComplianceEvaluationRequirementSource::where('compliance_evaluation_id', $evaluation->id)
            ->with('int_requirement_source:id,reference,name')
            ->orderBy('id')
            ->get()
            ->map(function ($cers) {
                return [
                    'id' => $cers->id,
                    'requirement_source_id' => $cers->requirement_source_id,
                    'note' => $cers->note,
                    'reference' => $cers->int_requirement_source?->reference,
                    'name' => $cers->int_requirement_source?->name,
                ];
            });

        return response()->json($sources);
    }

    /**
     * Compute statistics for an evaluation.
     */
    private function computeStatistics(int $evaluationId): array
    {
        $rows = ComplianceEvaluationRequirement::where('compliance_evaluation_id', $evaluationId)->get();

        $total = $rows->count();
        $pass = $rows->filter(fn($r) => $r->evaluated && $r->applicable)->count();
        $na = $rows->filter(fn($r) => $r->evaluated && !$r->applicable)->count();
        $open = $rows->filter(fn($r) => !$r->evaluated)->count();

        return [
            'requirements' => $total,
            'pass' => $pass,
            'fail' => 0, // legacy field - findings are tracked separately
            'na' => $na,
            'open' => $open,
        ];
    }
}

