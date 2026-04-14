<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsequenceLevel;
use App\Models\ProbabilityLevel;
use App\Models\RiskLevel;
use App\Models\RiskLevelMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssessmentSettingsRiskMappingController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', RiskLevelMapping::class);

        return response()->json([
            'mappings' => RiskLevelMapping::query()
                ->select(['probability_level_id', 'consequence_level_id', 'risk_level_id'])
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', RiskLevelMapping::class);

        $validated = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.probability_level_id' => ['required', 'integer', 'exists:probability_levels,id'],
            'mappings.*.consequence_level_id' => ['required', 'integer', 'exists:consequence_levels,id'],
            'mappings.*.risk_level_id' => ['required', 'integer', 'exists:risk_levels,id'],
        ]);

        $probabilityIds = ProbabilityLevel::query()->pluck('id')->map(static fn ($id): int => (int) $id)->values();
        $consequenceIds = ConsequenceLevel::query()->pluck('id')->map(static fn ($id): int => (int) $id)->values();
        $riskLevelCount = RiskLevel::query()->count();

        if ($probabilityIds->isEmpty() || $consequenceIds->isEmpty() || $riskLevelCount === 0) {
            throw ValidationException::withMessages([
                'mappings' => ['Probability levels, consequence levels, and risk levels must exist before mappings can be saved.'],
            ]);
        }

        $expectedPairs = [];
        foreach ($probabilityIds as $probabilityId) {
            foreach ($consequenceIds as $consequenceId) {
                $expectedPairs[$this->pairKey($probabilityId, $consequenceId)] = true;
            }
        }

        $seenPairs = [];
        $unknownPairs = [];
        foreach ($validated['mappings'] as $mapping) {
            $pairKey = $this->pairKey((int) $mapping['probability_level_id'], (int) $mapping['consequence_level_id']);

            if (! isset($expectedPairs[$pairKey])) {
                $unknownPairs[] = $pairKey;
                continue;
            }

            if (isset($seenPairs[$pairKey])) {
                throw ValidationException::withMessages([
                    'mappings' => ["Duplicate mapping for pair {$pairKey}."],
                ]);
            }

            $seenPairs[$pairKey] = [
                'probability_level_id' => (int) $mapping['probability_level_id'],
                'consequence_level_id' => (int) $mapping['consequence_level_id'],
                'risk_level_id' => (int) $mapping['risk_level_id'],
            ];
        }

        if ($unknownPairs !== []) {
            throw ValidationException::withMessages([
                'mappings' => ['Mappings include unknown probability/consequence pairs.'],
            ]);
        }

        $missingPairs = array_values(array_diff(array_keys($expectedPairs), array_keys($seenPairs)));
        if ($missingPairs !== []) {
            throw ValidationException::withMessages([
                'mappings' => ['All probability and consequence combinations must be mapped before saving.'],
                'missing_pairs' => $missingPairs,
            ]);
        }

        DB::transaction(function () use ($seenPairs): void {
            RiskLevelMapping::query()->delete();

            $timestamp = now();
            $rows = array_values(array_map(static function (array $mapping) use ($timestamp): array {
                return [
                    'probability_level_id' => $mapping['probability_level_id'],
                    'consequence_level_id' => $mapping['consequence_level_id'],
                    'risk_level_id' => $mapping['risk_level_id'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }, $seenPairs));

            RiskLevelMapping::query()->insert($rows);
        });

        return response()->json([
            'message' => 'Risk mappings saved.',
            'mappings' => array_values($seenPairs),
        ]);
    }

    private function pairKey(int $probabilityId, int $consequenceId): string
    {
        return $probabilityId.':'.$consequenceId;
    }
}

