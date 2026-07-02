<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Control;
use App\Models\Customer;
use App\Models\InformationType;
use App\Models\Process;
use App\Models\ProcessActivity;
use App\Models\Qualification;
use App\Models\QualificationRole;
use App\Models\RoleCompetence;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    /**
     * General profile information for the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'title'          => $user->title,
            'manager'        => $user->int_manager_user
                ? ['id' => $user->int_manager_user->id, 'name' => $user->int_manager_user->name]
                : null,
            'departments'    => $user->int_departments()->select('departments.id', 'departments.name')->orderBy('name')->get(),
            'direct_reports' => User::where('manager_user_id', $user->id)
                ->where('enabled', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Roles assigned to the authenticated user, including their process activities.
     */
    public function roles(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $roles = $user->int_roles()
            ->select('roles.id', 'roles.name', 'roles.description', 'roles.authorities')
            ->orderBy('roles.name')
            ->get();

        $result = [];
        foreach ($roles as $role) {
            $accountable = ProcessActivity::join('processes', 'processes.id', '=', 'process_activities.process_id')
                ->where('process_activities.accountable_role_id', $role->id)
                ->select(
                    'process_activities.id',
                    'process_activities.name',
                    'process_activities.process_id',
                    'processes.name as process_name'
                )
                ->orderBy('processes.name')
                ->orderBy('process_activities.name')
                ->get();

            $responsible = ProcessActivity::join('processes', 'processes.id', '=', 'process_activities.process_id')
                ->where('process_activities.responsible_role_id', $role->id)
                ->select(
                    'process_activities.id',
                    'process_activities.name',
                    'process_activities.process_id',
                    'processes.name as process_name'
                )
                ->orderBy('processes.name')
                ->orderBy('process_activities.name')
                ->get();

            $result[] = [
                'id'                      => $role->id,
                'name'                    => $role->name,
                'description'             => $role->description,
                'authorities'             => $role->authorities,
                'accountable_activities'  => $accountable,
                'responsible_activities'  => $responsible,
            ];
        }

        return response()->json($result);
    }

    /**
     * Qualifications for the authenticated user (achieved and missing mandatory ones).
     */
    public function qualifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $achieved = $user->int_qualification_user()
            ->with('int_qualification:id,name')
            ->orderBy('qualification_id')
            ->get()
            ->map(fn ($qu) => [
                'id'          => $qu->qualification_id,
                'name'        => $qu->int_qualification?->name,
                'finished_at' => $qu->finished_at?->format('Y-m-d'),
                'planned_at'  => $qu->planned_at?->format('Y-m-d'),
                'expires_at'  => $qu->expires_at?->format('Y-m-d'),
            ])
            ->sortBy('name')
            ->values();

        $roleIds = $user->int_roles()->pluck('roles.id');
        $mandatoryQualificationIds = QualificationRole::whereIn('role_id', $roleIds)
            ->where('mandatory', true)
            ->pluck('qualification_id')
            ->unique();

        $completedQualificationIds = $user->int_qualification_user()
            ->whereNotNull('finished_at')
            ->pluck('qualification_id');

        $missingIds = $mandatoryQualificationIds->diff($completedQualificationIds);

        $missing = Qualification::whereIn('id', $missingIds)
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        return response()->json([
            'achieved' => $achieved,
            'missing'  => $missing,
        ]);
    }

    /**
     * Competences for the authenticated user, including role requirements.
     */
    public function competences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $roleIds = $user->int_roles()->pluck('roles.id');

        $roleCompetences = RoleCompetence::whereIn('role_id', $roleIds)
            ->with([
                'int_competence:id,name,description',
                'int_acceptable_competence_level:id,name,ordinal',
                'int_desired_competence_level:id,name,ordinal',
            ])
            ->get()
            ->groupBy('competence_id');

        $userCompetences = $user->int_user_competence()
            ->with([
                'int_competence_level:id,name,ordinal',
                'int_competence:id,name,description',
            ])
            ->get()
            ->keyBy('competence_id');

        $allCompetenceIds = $roleCompetences->keys()->merge($userCompetences->keys())->unique();

        $result = [];
        foreach ($allCompetenceIds as $competenceId) {
            $userComp  = $userCompetences->get($competenceId);
            $roleComps = $roleCompetences->get($competenceId, collect());

            $competence = $userComp?->int_competence ?? $roleComps->first()?->int_competence;

            $maxAcceptableOrdinal = 0;
            $maxDesiredOrdinal    = 0;
            $acceptableLevelName  = null;
            $desiredLevelName     = null;
            $isMandatory          = $roleComps->isNotEmpty();

            foreach ($roleComps as $rc) {
                $accLevel = $rc->int_acceptable_competence_level;
                $desLevel = $rc->int_desired_competence_level;

                if ($accLevel && $accLevel->ordinal > $maxAcceptableOrdinal) {
                    $maxAcceptableOrdinal = $accLevel->ordinal;
                    $acceptableLevelName  = $accLevel->name;
                }
                if ($desLevel && $desLevel->ordinal > $maxDesiredOrdinal) {
                    $maxDesiredOrdinal = $desLevel->ordinal;
                    $desiredLevelName  = $desLevel->name;
                }
            }

            $achievedOrdinal   = $userComp?->int_competence_level?->ordinal ?? 0;
            $achievedLevelName = $userComp?->int_competence_level?->name;
            $evaluated         = $userComp !== null;

            $result[] = [
                'id'                   => $competenceId,
                'name'                 => $competence?->name ?? '',
                'description'          => $competence?->description ?? '',
                'is_mandatory'         => $isMandatory,
                'achieved_level_name'  => $achievedLevelName,
                'acceptable_level_name' => $acceptableLevelName,
                'desired_level_name'   => $desiredLevelName,
                'acceptable_ok'        => $maxAcceptableOrdinal === 0 || $achievedOrdinal >= $maxAcceptableOrdinal,
                'desired_ok'           => $maxDesiredOrdinal === 0 || $achievedOrdinal >= $maxDesiredOrdinal,
                'evaluated'            => $evaluated,
                'note'                 => $userComp?->note,
                'updated_by'           => $userComp?->updated_by_name,
                'updated_at'           => $userComp?->updated_at?->format('Y-m-d'),
            ];
        }

        usort($result, static fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return response()->json($result);
    }

    /**
     * Objects the authenticated user is responsible for.
     */
    public function responsibilities(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'processes'        => Process::where('responsible_user_id', $user->id)->orderBy('name')->select('id', 'name')->get(),
            'information_types' => InformationType::where('responsible_user_id', $user->id)->orderBy('name')->select('id', 'name')->get(),
            'assets'           => Asset::where('responsible_user_id', $user->id)->orderBy('name')->select('id', 'name')->get(),
            'customers'        => Customer::where('responsible_user_id', $user->id)->orderBy('name')->select('id', 'name')->get(),
            'suppliers'        => Supplier::where('responsible_user_id', $user->id)->orderBy('name')->select('id', 'name')->get()
                ->where('responsible_user_id', $user->id)
                ->orderBy('name')
                ->select('id', 'name')
                ->get(),
        ]);
    }
}

