<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DocumentVersionActionController extends Controller
{
    /**
     * Mark a document version as finished and request approval.
     */
    public function finish(string $versionId): JsonResponse
    {
        $version = DocumentVersion::query()->findOrFail($versionId);

        Gate::authorize('update', $version);

        // Check if already finished
        if ($version->finished_at !== null) {
            return response()->json(
                ['message' => 'Document version is already finished.'],
                Response::HTTP_CONFLICT
            );
        }

        // Update version
        $version->update(['finished_at' => now()]);

        return response()->json($version->fresh());
    }

    /**
     * Approve and publish a document version.
     */
    public function approve(string $versionId): JsonResponse
    {
        $version = DocumentVersion::query()->findOrFail($versionId);

        // Check if user is the approver
        if ($version->approver_id !== auth()->id()) {
            return response()->json(
                ['message' => 'You are not authorized to approve this version.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Check if already approved
        if ($version->approved_at !== null) {
            return response()->json(
                ['message' => 'Document version is already approved.'],
                Response::HTTP_CONFLICT
            );
        }

        // Check if finished
        if ($version->finished_at === null) {
            return response()->json(
                ['message' => 'Document version must be finished before approval.'],
                Response::HTTP_CONFLICT
            );
        }

        // Update version
        $version->update(['approved_at' => now()]);

        return response()->json($version->fresh());
    }

    /**
     * Reject a document version.
     */
    public function reject(string $versionId): JsonResponse
    {
        $version = DocumentVersion::query()->findOrFail($versionId);

        // Check if user is the approver
        if ($version->approver_id !== auth()->id()) {
            return response()->json(
                ['message' => 'You are not authorized to reject this version.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Check if already approved
        if ($version->approved_at !== null) {
            return response()->json(
                ['message' => 'Cannot reject an already approved version.'],
                Response::HTTP_CONFLICT
            );
        }

        // Delete the version (or soft delete if using soft deletes)
        $version->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

