<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class riskreassess extends Command
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
       // Output
       foreach(\App\Models\Risk::whereNotNull('assessed_at')->whereNull('replacedby_id')->get() as $risk)
       {
          $risklevel = $risk->int_risklevel();
          if(null == $risklevel)
             continue;
          
          // Skip if part of an archived risk project
          if((null !== $risk->risk_project_id) && (null !== $risk->int_risk_project->archived_at))
             continue;

          if(0 < count($risk->int_control_actions))
          {
             // Have planned actions and this is to be reassessed
             if(0 < $risklevel->reassessment_days_withplans)
             {
                $duetime = strtotime('+'.$risklevel->reassessment_days_withplans.' DAYS', strtotime($risk->assessed_at));
                if($duetime < time())
                {
                   $risk->replace(__("more than")." ".$risklevel->reassessment_days_withplans." ".__("days have passed since last assessment"));
                   $this->info("Risk ".$risk->name." was opened for reassessment due to having plans and it was due time for reassessment");
                }
             }
          }
          else
          {
             // No planned actions
             if(0 < $risklevel->reassessment_days_withoutplans)
             {
                $duetime = strtotime('+'.$risklevel->reassessment_days_withoutplans.' DAYS', strtotime($risk->assessed_at));
                if($duetime < time())
                {
                   $risk->replace(__("more than")." ".$risklevel->reassessment_days_withoutplans." ".__("days have passed since last assessment"));
                   $this->info("Risk ".$risk->name." was opened for reassessment due to not having plans but it was due time for reassessment");
                }
             }
             
          }
       }
       return Command::SUCCESS;
    }
}
