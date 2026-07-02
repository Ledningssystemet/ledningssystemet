<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MenuBadgeController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Cache::remember('menu-badge-status:'.auth()->user()->id, 60, function () {
            $user = Auth::user();
            $menus = [
                'access-groups' => ['level' => 'unknown', 'model' => \App\Models\AccessGroup::class],
                'activities' => ['level' => 'unknown', 'model' => \App\Models\Activity::class],
                'activity-flows' => ['level' => 'unknown', 'model' => \App\Models\ActivityFlow::class],
                'agreements' => ['level' => 'unknown', 'model' => \App\Models\Agreement::class],
                'assets' => ['level' => 'unknown', 'model' => \App\Models\Asset::class],
                'chemical-register' => ['level' => 'unknown', 'model' => \App\Models\Chemical::class],
                'competences' => ['level' => 'unknown', 'model' => \App\Models\Competence::class],
                'compliance-evaluation' => ['level' => 'unknown', 'model' => \App\Models\ComplianceEvaluation::class],
                'control-actions' => ['level' => 'unknown', 'model' => \App\Models\ControlAction::class],
                'controls' => ['level' => 'unknown', 'model' => \App\Models\Control::class],
                'customers' => ['level' => 'unknown', 'model' => \App\Models\Customer::class],
                'departments' => ['level' => 'unknown', 'model' => \App\Models\Department::class],
                'documents' => ['level' => 'unknown', 'model' => \App\Models\LibraryDocument::class],
                'employees' => ['level' => 'unknown', 'model' => \App\Models\User::class],
                'incidents' => ['level' => 'unknown', 'model' => \App\Models\Incident::class],
                'information-types' => ['level' => 'unknown', 'model' => \App\Models\InformationType::class],
                'objectives' => ['level' => 'unknown', 'model' => \App\Models\Objective::class],
                'observations' => ['level' => 'unknown', 'model' => \App\Models\Finding::class],
                'processes' => ['level' => 'unknown', 'model' => \App\Models\Process::class],
                'process-performance' => ['level' => 'unknown', 'model' => \App\Models\ProcessPerformanceMetric::class],
                'Project types' => ['level' => 'unknown', 'model' => \App\Models\ProjectType::class],
                'projects' => ['level' => 'unknown', 'model' => \App\Models\Project::class],
                'qualifications' => ['level' => 'unknown', 'model' => \App\Models\Qualification::class],
                'requirement-sources' => ['level' => 'unknown', 'model' => \App\Models\RequirementSource::class],
                'risks' => ['level' => 'unknown', 'model' => \App\Models\Risk::class],
                'roles' => ['level' => 'unknown', 'model' => \App\Models\Role::class],
                'sites' => ['level' => 'unknown', 'model' => \App\Models\Site::class],
                'supplier-categories' => ['level' => 'unknown', 'model' => \App\Models\SupplierCategory::class],
                'suppliers' => ['level' => 'unknown', 'model' => \App\Models\Supplier::class],
                'sustainability-aspects' => ['level' => 'unknown', 'model' => \App\Models\SustainabilityAspect::class],
                'tags' => ['level' => 'unknown', 'model' => \App\Models\Tag::class],
                'users' => ['level' => 'unknown', 'model' => \App\Models\User::class],
            ];

            $items = [];

            foreach (array_keys($menus) as $menu) {
                // Check if the user has access to view this menu item
                if (!Gate::allows('viewAny', $menus[$menu]['model']))
                    continue;

                // Check if the model has a static getItemsStatus function
                if (!method_exists($menus[$menu]['model'], 'getItemsStatus'))
                    continue;

                // Get item status
                try {
                    $status = $menus[$menu]['model']::getItemsStatus(null, $user, false);
                } catch (Throwable $e) {
                    // If there's an error, log it and skip this menu item
                    \Log::error("Error getting status for menu item '$menu': " . $e->getMessage());
                    continue;
                }
                // Calculate highest level for the menu item
                $level = $menus[$menu]['level'];

                foreach ($status as $itemStatus) {
                    if ($itemStatus['level'] === 'danger') {
                        $level = 'danger';
                        break;
                    } elseif ($itemStatus['level'] === 'warning' && $level !== 'danger') {
                        $level = 'warning';
                    } elseif ($itemStatus['level'] === 'info' && $level === 'unknown') {
                        $level = 'info';
                    }
                }

                $items[$menu] = $level;
            }

            return $items;
        });

        return response()->json($items);

    }
}

