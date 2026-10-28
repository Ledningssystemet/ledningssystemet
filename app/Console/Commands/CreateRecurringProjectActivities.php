<?php

namespace Ledningssystemet\Ledningssystemet\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectActivity;
use Ledningssystemet\Ledningssystemet\Models\RecurringProjectActivity;

class CreateRecurringProjectActivities extends Command
{
    protected $signature = 'ledningssystemet:recurringprojectactivities';

    protected $description = 'Create scheduled project activities unless an unfinished activity already exists';

    public function handle(): int
    {
        $today = today();
        $created = 0;
        RecurringProjectActivity::query()->select(['id', 'project_id'])->chunkById(100, function ($items) use ($today, &$created): void {
            foreach ($items as $item) {
                $created += DB::transaction(function () use ($item, $today): int {
                    $project = Project::whereKey($item->project_id)->lockForUpdate()->first();
                    if (! $project || $project->archived_at) {
                        return 0;
                    }
                    $recurring = RecurringProjectActivity::whereKey($item->id)->lockForUpdate()->first();
                    if (! $recurring || ! $recurring->isDueOn($today)
                        || $recurring->last_generated_on >= $today->toDateString()
                        || $recurring->int_activities()->where(function ($query) use ($today): void {
                            $query->whereNull('completed_at')->orWhere('completed_at', '>=', $today);
                        })->exists()) {
                        return 0;
                    }

                    ProjectActivity::create([
                        ...$recurring->only(['project_id', 'name', 'description', 'responsible_user_id', 'estimated_cost', 'estimated_hours']),
                        'recurring_project_activity_id' => $recurring->id,
                        'startdate' => $today->toDateString(),
                        'due' => $today->toDateString(),
                    ]);
                    $recurring->last_generated_on = $today->toDateString();
                    $recurring->save();

                    return 1;
                });
            }
        });

        $this->info("Created {$created} recurring project activities.");

        return self::SUCCESS;
    }
}
