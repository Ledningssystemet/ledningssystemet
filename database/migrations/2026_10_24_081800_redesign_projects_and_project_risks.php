<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ledningssystemet\Ledningssystemet\Models\Activity;
use Ledningssystemet\Ledningssystemet\Models\ActivityLog;
use Ledningssystemet\Ledningssystemet\Models\Control;
use Ledningssystemet\Ledningssystemet\Models\ControlAction;
use Ledningssystemet\Ledningssystemet\Models\ProjectRisk;
use Ledningssystemet\Ledningssystemet\Models\Risk;

return new class extends Migration
{
    public function up(): void
    {
        // Merge the two legacy description fields before removing them.
        if (! Schema::hasColumn('projects', 'description')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->text('description')->nullable();
            });
        }

        DB::table('projects')
            ->select(['id', 'scopedescription', 'purposedescription', 'department_id'])
            ->orderBy('id')
            ->chunkById(250, function ($projects): void {
                foreach ($projects as $project) {
                    $parts = array_values(array_filter(
                        [$project->scopedescription, $project->purposedescription],
                        static fn ($value): bool => $value !== null && $value !== '',
                    ));

                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['description' => $parts ? implode("\n\n", $parts) : null]);

                    // Get department name for logging
                   $departmentName = DB::table('departments')
                        ->where('id', $project->department_id)
                        ->value('name');

                    // Add an activity log entry for the description merge.
                    ActivityLog::addMessage(
                        "The project was previously related to the department ".$departmentName." and had its scope and purpose descriptions merged into a single description field during a database migration.",
                        (new \Ledningssystemet\Ledningssystemet\Models\Project)->forceFill(['id' => $project->id]),
                    );
                }
            });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign('projects_department_id_foreign');
            $table->dropColumn(['scopedescription', 'purposedescription', 'department_id']);
        });

        Schema::create('project_risks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('scenariodescription')->nullable();
            $table->text('consequencedescription')->nullable();
            $table->foreignId('riskowner_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('assessed_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('probability_id')->nullable();
            $table->unsignedBigInteger('consequence_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('post_probability_id')->nullable();
            $table->unsignedBigInteger('post_consequence_id')->nullable();
            $table->foreign('probability_id')->references('id')->on('probability_levels')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('consequence_id')->references('id')->on('consequence_levels')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('post_probability_id')->references('id')->on('probability_levels')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('post_consequence_id')->references('id')->on('consequence_levels')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('project_risk_controls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_risk_id')->constrained('project_risks')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['project_risk_id', 'control_id']);
        });

        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('startdate')->nullable();
            $table->date('due')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_risk_project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_risk_id')->constrained('project_risks')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('project_activity_id')->constrained('project_activities')->cascadeOnDelete()->cascadeOnUpdate();
        });

        // Copy the active project risks, their controls, and their related history.
        $projectRisks = DB::table('risks')
            ->whereNotNull('project_id')
            ->whereNull('replacedby_id')
            ->orderBy('id')
            ->get();

        foreach ($projectRisks as $risk) {
            DB::table('project_risks')->insert([
                'id' => $risk->id,
                'name' => $risk->name,
                'scenariodescription' => $risk->scenariodescription,
                'consequencedescription' => $risk->consequencedescription,
                'riskowner_id' => $risk->riskowner_id,
                'assessed_at' => $risk->assessed_at,
                'created_at' => $risk->created_at,
                'updated_at' => $risk->updated_at,
                'probability_id' => $risk->probability_id,
                'consequence_id' => $risk->consequence_id,
                'note' => $risk->assessmentcomment,
                'project_id' => $risk->project_id,
                'post_probability_id' => $risk->post_probability_id,
                'post_consequence_id' => $risk->post_consequence_id,
            ]);

            foreach (DB::table('control_risks')->where('risk_id', $risk->id)->distinct()->get(['control_id']) as $controlRisk) {
                DB::table('project_risk_controls')->insert([
                    'project_risk_id' => $risk->id,
                    'control_id' => $controlRisk->control_id,
                ]);
            }
            // Turn each project-risk action mapping into its own linked project activity.
            foreach (DB::table('control_action_mappings')->where('risk_id', $risk->id)->get() as $mapping) {
                $controlAction = DB::table('control_actions')->where('id', $mapping->control_action_id)->first();
                if (! $controlAction) {
                    continue;
                }

                $activityId = DB::table('project_activities')->insertGetId([
                    'project_id' => $risk->project_id,
                    'responsible_user_id' => $controlAction->responsible_id,
                    'name' => $controlAction->name,
                    'description' => $controlAction->description,
                    'due' => $controlAction->due,
                    'estimated_cost' => $controlAction->estimated_cost,
                    'completed_at' => $controlAction->finished_at,
                    'created_at' => $controlAction->created_at,
                    'updated_at' => $controlAction->updated_at,
                ]);
                DB::table('project_risk_project_activities')->insert([
                    'project_risk_id' => $risk->id,
                    'project_activity_id' => $activityId,
                ]);

                $control = Schema::hasColumn('control_actions', 'control_id')
                   ? DB::table('controls')->where('id', $controlAction->control_id)->first()
                   : null;
                $controlDescription = $control
                   ? "control {$control->id} ({$control->name})"
                   : 'the former control';
                $activitySubject = (new Activity)->forceFill(['id' => $activityId]);
                ActivityLog::addMessage(
                    "This project activity was created from former project control action {$controlAction->id} ({$controlAction->name}), related to {$controlDescription} and project risk RISK-{$risk->id}.",
                    $activitySubject,
                );
                ActivityLog::addMessage(
                    "This control action was migrated from project {$risk->project_id} into project activity {$activityId}.",
                    (new ControlAction)->forceFill(['id' => $controlAction->id]),
                );

                if ($controlAction->finished_at) {
                    ActivityLog::addMessage(
                        "The project activity was completed from control action {$controlAction->name} on {$controlAction->finished_at}.",
                        $activitySubject,
                    );
                    ActivityLog::addMessage(
                        "The control action was completed and project activity {$activityId} was created as completed.",
                        (new ControlAction)->forceFill(['id' => $controlAction->id]),
                    );
                } else {
                    ActivityLog::addMessage(
                        "This pending project activity was created from control action {$controlAction->name} migrated from project {$risk->project_id}.",
                        $activitySubject,
                    );
                }

                DB::table('control_action_mappings')
                    ->where('control_action_id', $controlAction->id)
                    ->where('risk_id', $risk->id)
                    ->delete();
                ActivityLog::addMessage(
                    "This control action was removed from project risk RISK-{$risk->id} because project activity {$activityId} was created.",
                    (new ControlAction)->forceFill(['id' => $controlAction->id]),
                );

                if (! DB::table('control_action_mappings')->where('control_action_id', $controlAction->id)->exists()) {
                    if ($control && Schema::hasColumn('control_actions', 'control_id')) {
                        ActivityLog::addMessage(
                            "Control action {$controlAction->name} was removed because project activity {$activityId} was created.",
                            (new Control)->forceFill(['id' => $control->id]),
                        );
                    }
                    DB::table('control_actions')->where('id', $controlAction->id)->delete();
                }
            }

            // Delete this legacy risk and every previous assessment in its replacement chain.
            $legacyRisk = $risk;
            $deletedRiskIds = [];
            while ($legacyRisk && ! isset($deletedRiskIds[$legacyRisk->id])) {
                $deletedRiskIds[$legacyRisk->id] = true;
                if ((int) $legacyRisk->id !== (int) $risk->id) {
                    ActivityLog::addMessage(
                        "This project risk replaced legacy risk RISK-{$legacyRisk->id}, which was deleted during migration.",
                        (new ProjectRisk)->forceFill(['id' => $risk->id]),
                    );
                }

                ActivityLog::addMessage(
                    "This legacy risk was deleted because project risk RISK-{$risk->id} was migrated.",
                    (new Risk)->forceFill(['id' => $legacyRisk->id]),
                );
                $previousRiskId = $legacyRisk->replacing_id;
                DB::table('risks')->where('id', $legacyRisk->id)->delete();
                $legacyRisk = $previousRiskId
                   ? DB::table('risks')->where('id', $previousRiskId)->first()
                   : null;
            }
        }

        // Remove legacy project-risk links and the obsolete project department field.
        Schema::table('risks', function (Blueprint $table): void {
            $table->dropForeign('risks_project_id_foreign');
            $table->dropColumn('project_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This project data migration is irreversible because descriptions, project risks, and control actions were consolidated.');
    }
};
