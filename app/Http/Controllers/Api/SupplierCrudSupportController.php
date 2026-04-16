<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\SupplierRequirement;
use App\Models\SupplierSupplierCategory;
use App\Models\SupplierSupplierRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupplierCrudSupportController extends Controller
{
    public function categoryOptions(): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $categories = SupplierCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function categories(Supplier $supplier): JsonResponse
    {
        Gate::authorize('view', $supplier);

        $statuses = SupplierSupplierCategory::query()
            ->where('supplier_id', $supplier->id)
            ->get(['supplier_category_id', 'applicable', 'updated_by_name', 'updated_at'])
            ->keyBy(static fn (SupplierSupplierCategory $status): int => (int) $status->supplier_category_id);

        $rows = SupplierCategory::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (SupplierCategory $category) use ($statuses): array {
                /** @var SupplierSupplierCategory|null $status */
                $status = $statuses->get((int) $category->id);

                return [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'applicable' => $status?->applicable,
                    'updated_by_name' => $status?->updated_by_name,
                    'updated_at' => $status?->updated_at?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function updateCategory(Request $request, Supplier $supplier, SupplierCategory $category): JsonResponse
    {
        Gate::authorize('update', $supplier);

        $validated = $request->validate([
            'applicable' => ['required', 'boolean'],
        ]);

        $status = SupplierSupplierCategory::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'supplier_category_id' => $category->id,
            ],
            [
                'applicable' => $validated['applicable'],
                'updated_by_name' => (string) ($request->user()?->name ?? 'System'),
            ]
        );

        return response()->json([
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'applicable' => $status->applicable,
            'updated_by_name' => $status->updated_by_name,
            'updated_at' => $status->updated_at?->toISOString(),
        ]);
    }

    public function evaluation(Supplier $supplier): JsonResponse
    {
        Gate::authorize('view', $supplier);

        $rows = $this->buildEvaluationRows($supplier);

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function updateEvaluation(Request $request, Supplier $supplier, SupplierRequirement $requirement): JsonResponse
    {
        Gate::authorize('update', $supplier);

        $applicableCategoryIds = SupplierSupplierCategory::query()
            ->where('supplier_id', $supplier->id)
            ->where('applicable', true)
            ->pluck('supplier_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        abort_unless(in_array((int) $requirement->supplier_category_id, $applicableCategoryIds, true), 404);

        $validated = $request->validate([
            'satisfactory' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $evaluation = SupplierSupplierRequirement::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'supplier_requirement_id' => $requirement->id,
            ],
            [
                'updated_by_name' => (string) ($request->user()?->name ?? 'System'),
                'note' => $validated['note'] ?? null,
                'satisfactory' => $validated['satisfactory'],
            ]
        );

        return response()->json($this->serializeEvaluationRow($requirement, $evaluation));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildEvaluationRows(Supplier $supplier): array
    {
        $applicableCategoryIds = SupplierSupplierCategory::query()
            ->where('supplier_id', $supplier->id)
            ->where('applicable', true)
            ->pluck('supplier_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($applicableCategoryIds === []) {
            return [];
        }

        $requirements = SupplierRequirement::query()
            ->whereIn('supplier_category_id', $applicableCategoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'reassessment', 'supplier_category_id']);

        $evaluations = SupplierSupplierRequirement::query()
            ->where('supplier_id', $supplier->id)
            ->whereIn('supplier_requirement_id', $requirements->pluck('id')->all())
            ->get(['supplier_requirement_id', 'updated_by_name', 'updated_at', 'note', 'satisfactory'])
            ->keyBy(static fn (SupplierSupplierRequirement $evaluation): int => (int) $evaluation->supplier_requirement_id);

        return $requirements
            ->map(function (SupplierRequirement $requirement) use ($evaluations): array {
                /** @var SupplierSupplierRequirement|null $evaluation */
                $evaluation = $evaluations->get((int) $requirement->id);

                return $this->serializeEvaluationRow($requirement, $evaluation);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvaluationRow(SupplierRequirement $requirement, ?SupplierSupplierRequirement $evaluation): array
    {
        return [
            'id' => (int) $requirement->id,
            'name' => (string) $requirement->name,
            'description' => $requirement->description,
            'reassessment' => (bool) $requirement->reassessment,
            'satisfactory' => $evaluation?->satisfactory,
            'note' => $evaluation?->note,
            'evaluated_at' => $evaluation?->updated_at?->toISOString(),
            'evaluated_by_name' => $evaluation?->updated_by_name,
        ];
    }
}

