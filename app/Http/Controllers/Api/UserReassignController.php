<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Asset;
use App\Models\Control;
use App\Models\ControlAction;
use App\Models\Incident;
use App\Models\InformationType;
use App\Models\Objective;
use App\Models\Process;
use App\Models\ProcessPerformanceMetric;
use App\Models\Risk;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserReassignController extends Controller
{
    public function store(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'activities' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'assets' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'controls' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'control_actions' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'incidents' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'information_types' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'objectives' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'processes' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'process_performance_metrics' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'risks' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'suppliers' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
        ]);

        $result = DB::transaction(function () use ($user, $data): array {
            $moved = [
                'activities' => 0,
                'assets' => 0,
                'controls' => 0,
                'control_actions' => 0,
                'incidents' => 0,
                'information_types' => 0,
                'objectives' => 0,
                'processes' => 0,
                'process_performance_metrics' => 0,
                'risks' => 0,
                'suppliers' => 0,
            ];

            if (isset($data['activities'])) {
                $moved['activities'] = Activity::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['activities']]);
            }

            if (isset($data['assets'])) {
                $moved['assets'] = Asset::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['assets']]);
            }

            if (isset($data['controls'])) {
                $moved['controls'] = Control::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['controls']]);
            }

            if (isset($data['control_actions'])) {
                $moved['control_actions'] = ControlAction::query()
                    ->where('responsible_id', $user->id)
                    ->update(['responsible_id' => (int) $data['control_actions']]);
            }

            if (isset($data['incidents'])) {
                $moved['incidents'] = Incident::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['incidents']]);
            }

            if (isset($data['information_types'])) {
                $moved['information_types'] = InformationType::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['information_types']]);
            }

            if (isset($data['objectives'])) {
                $moved['objectives'] = Objective::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['objectives']]);
            }

            if (isset($data['processes'])) {
                $moved['processes'] = Process::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['processes']]);
            }

            if (isset($data['process_performance_metrics'])) {
                $moved['process_performance_metrics'] = ProcessPerformanceMetric::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['process_performance_metrics']]);
            }

            if (isset($data['risks'])) {
                $moved['risks'] = Risk::query()
                    ->where('riskowner_id', $user->id)
                    ->update(['riskowner_id' => (int) $data['risks']]);
            }

            if (isset($data['suppliers'])) {
                $moved['suppliers'] = Supplier::query()
                    ->where('responsible_user_id', $user->id)
                    ->update(['responsible_user_id' => (int) $data['suppliers']]);
            }

            return $moved;
        });

        return response()->json([
            'message' => __('api.reassign.ok'),
            'moved' => $result,
        ]);
    }
}

