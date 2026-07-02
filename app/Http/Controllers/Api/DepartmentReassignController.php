<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Finding;
use App\Models\Process;
use App\Models\Risk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentReassignController extends Controller
{
    public function store(Request $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $data = $request->validate([
            'processes' => ['nullable', 'integer', 'min:1', 'exists:departments,id'],
            'risks' => ['nullable', 'integer', 'min:1', 'exists:departments,id'],
            'findings' => ['nullable', 'integer', 'min:1', 'exists:departments,id'],
        ]);

        $result = DB::transaction(function () use ($department, $data): array {
            $movedProcesses = 0;
            $movedRisks = 0;
            $movedFindings = 0;

            if (isset($data['processes'])) {
                $movedProcesses = Process::query()
                    ->where('department_id', $department->id)
                    ->update(['department_id' => (int) $data['processes']]);
            }

            if (isset($data['risks'])) {
                $movedRisks = Risk::query()
                    ->where('department_id', $department->id)
                    ->update(['department_id' => (int) $data['risks']]);
            }

            if (isset($data['findings'])) {
                $movedFindings = Finding::query()
                    ->where('department_id', $department->id)
                    ->update(['department_id' => (int) $data['findings']]);
            }

            return [
                'processes' => $movedProcesses,
                'risks' => $movedRisks,
                'findings' => $movedFindings,
            ];
        });

        return response()->json([
            'message' => __('api.reassign.ok'),
            'moved' => $result,
        ]);
    }
}

