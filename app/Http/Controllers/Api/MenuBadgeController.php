<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

class MenuBadgeController extends Controller
{
    public function index(): JsonResponse
    {
        $user  = Auth::user();
        $items      = [];
        $categories = [];

        // ── Mina uppgifter (pending activities assigned to user) ─────────
        try {
            if (Gate::allows('viewAny', \App\Models\Activity::class)) {
                $count = \App\Models\Activity::query()
                    ->where('responsible_user_id', $user->id)
                    ->whereNull('completed_at')
                    ->where('due', '>=', now())
                    ->count();

                if ($count > 0) {
                    $items['my-tasks'] = [
                        'count'    => (string) $count,
                        'severity' => 'danger',
                    ];
                }
            }
        } catch (Throwable) {
            // Silently skip if the table/model is unavailable
        }

        // ── Avvikelser & Förbättringar (open findings) ───────────────────
        try {
            if (Gate::allows('viewAny', \App\Models\Finding::class)) {
                $count = \App\Models\Finding::query()
                    ->whereNull('closed_at')
                    ->count();

                if ($count > 0) {
                    $items['avvikelser'] = [
                        'count'    => (string) $count,
                        'severity' => 'warning',
                    ];

                    // Merge into "Risk & Förbättring" category badge
                    $existing = (int) ($categories['Risk & Förbättring']['count'] ?? 0);
                    $categories['Risk & Förbättring'] = [
                        'count'    => (string) ($existing + $count),
                        'severity' => 'warning',
                    ];
                }
            }
        } catch (Throwable) {
            // Silently skip if the table/model is unavailable
        }

        // ── Riskhantering (open risks without accepted treatment) ─────────
        try {
            if (Gate::allows('viewAny', \App\Models\Risk::class)) {
                $count = \App\Models\Risk::query()
                    ->whereNull('treatment_accepted_at')
                    ->count();

                if ($count > 0) {
                    $items['riskhantering'] = [
                        'count'    => (string) $count,
                        'severity' => 'warning',
                    ];

                    $existing = (int) ($categories['Risk & Förbättring']['count'] ?? 0);
                    $categories['Risk & Förbättring'] = [
                        'count'    => (string) ($existing + $count),
                        'severity' => 'warning',
                    ];
                }
            }
        } catch (Throwable) {
            // Silently skip if the table/model is unavailable
        }

        return response()->json([
            'items'      => $items,
            'categories' => $categories,
        ]);
    }
}

