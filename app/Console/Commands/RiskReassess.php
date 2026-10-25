<?php

namespace Ledningssystemet\Ledningssystemet\Console\Commands;

use Illuminate\Console\Command;
use Ledningssystemet\Ledningssystemet\Models\ProjectRisk;
use Ledningssystemet\Ledningssystemet\Models\Risk;

class RiskReassess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledningssystemet:risksreassess';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Open risks for re-assessment if it is time';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
       foreach (Risk::whereNotNull('assessed_at')->whereNull('replacedby_id')->get() as $risk) {
          $this->reassessRisk($risk, $risk->int_control_actions()->exists());
       }

       foreach (ProjectRisk::whereNotNull('assessed_at')
          ->whereHas('int_project', fn ($query) => $query->whereNull('archived_at'))
          ->get() as $risk) {
          $this->reassessRisk(
             $risk,
             $risk->int_activities()->exists(),
          );
       }

       return Command::SUCCESS;
    }

    private function reassessRisk(Risk|ProjectRisk $risk, bool $hasPlans): void
    {
       $riskLevel = $risk->int_risklevel();
       if (!$riskLevel) {
          return;
       }

       $days = $hasPlans
          ? $riskLevel->reassessment_days_withplans
          : $riskLevel->reassessment_days_withoutplans;
       if ($days <= 0 || strtotime("+{$days} DAYS", strtotime($risk->assessed_at)) >= time()) {
          return;
       }

       $risk->replace(__('more than') . ' ' . $days . ' ' . __('days have passed since last assessment'));
       $this->info("Risk {$risk->name} was opened for reassessment due to its reassessment interval");
    }
}
