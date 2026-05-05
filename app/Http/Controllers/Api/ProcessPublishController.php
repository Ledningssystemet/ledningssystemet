<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Services\Bpmn\ProcessMapPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProcessPublishController extends Controller
{
    public function store(Request $request, Process $process, ProcessMapPublisher $publisher): JsonResponse
    {
        Gate::authorize('update', $process);

        $data = $request->validate([
            'bpmn' => ['required', 'string'],
        ]);

        $publisher->publish($process, $data['bpmn']);

        return response()->json($process->fresh());
    }
}

