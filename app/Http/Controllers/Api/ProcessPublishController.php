<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Services\Bpmn\BpmnPublishValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProcessPublishController extends Controller
{
    public function store(Request $request, Process $process, BpmnPublishValidator $validator): JsonResponse
    {
        Gate::authorize('update', $process);

        $data = $request->validate([
            'bpmn' => ['required', 'string'],
        ]);

        $validator->validateForPublish($data['bpmn']);

        if (($process->bpmn ?? null) !== $data['bpmn']) {
            throw ValidationException::withMessages([
                'publishedbpmn' => ['pages.process_editor.validation.save_before_publish'],
            ]);
        }


        $process->fill([
            'bpmn' => $data['bpmn'],
            'publishedbpmn' => $data['bpmn'],
        ]);
        $process->save();

        return response()->json($process->fresh());
    }
}

