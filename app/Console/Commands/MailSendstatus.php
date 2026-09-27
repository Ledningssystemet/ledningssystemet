<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailSendstatus extends Command
{
   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'ledningssystemet:sendstatusmail';

   /**
    * The console command description.
    *
    * @var string
    */
   protected $description = 'Send status email to all users';

   /**
    * Execute the console command.
    *
    * @return int
    */
   public function handle()
   {
      $this->info('Starting status E-mail transmission');

      foreach (\App\Models\User::where('enabled', 1)->get() as $user) {
         // Check if user wants email today
         $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
         $today = $weekdays[intval(Date("w"))];
         $this->info('Today is ' . $today);

         $userprefs = $user->getUserCommunicationPreferences();

         if (!$userprefs[$today])
         {
            $this->info('Will not send to ' . $user->email . ' due to user preferences');
            continue;
         }

         $sections = $this->getStatusSections($user);
         $dueItems = $this->getDueItems($user);

         if (0 == count($sections) && 0 == count($dueItems))
         {
            $this->info('Will not send to ' . $user->email . ' since there is nothing to report');
            continue;
         }

         $this->info('Sending to ' . $user->email);
         Mail::to($user->email)->send(new \App\Mail\StatusOverview($user, $sections, $dueItems));
      }

      $this->info('Status E-mail transmission complete');

      return Command::SUCCESS;
   }

   /**
    * Collect status issues for the user, grouped by section header.
    * Sections without issues are omitted.
    *
    * @return array<string, array>
    */
   private function getStatusSections(\App\Models\User $user): array
   {
      $sectionModels = [
         'Inventory' => [
            \App\Models\RequirementSource::class,
            \App\Models\Process::class,
            \App\Models\InformationType::class,
            \App\Models\Asset::class,
            \App\Models\Customer::class,
            \App\Models\Supplier::class,
            \App\Models\Agreement::class,
            \App\Models\Control::class,
            \App\Models\ProcessSustainabilityAspect::class,
            \App\Models\Chemical::class,
         ],
         'Assess and mitigate' => [
            \App\Models\RiskProject::class,
            \App\Models\Risk::class,
            \App\Models\ComplianceEvaluation::class,
            \App\Models\Finding::class,
            \App\Models\Incident::class,
            \App\Models\ControlAction::class,
         ],
         'Measure and improve' => [
            \App\Models\ProcessPerformanceMetric::class,
            \App\Models\Objective::class,
         ],
         'Employee management' => [
            \App\Models\Employee::class,
            \App\Models\EmployeeRole::class,
            \App\Models\Competence::class,
         ],
         'Coordination' => [
            \App\Models\Activity::class,
            \App\Models\LibraryDocument::class,
         ],
         'System settings' => [
            \App\Models\User::class,
            \App\Models\Site::class,
            \App\Models\Department::class,
            \App\Models\Role::class,
            \App\Models\AccessGroup::class,
         ],
      ];

      $sections = [];
      foreach ($sectionModels as $header => $models) {
         $issues = [];
         foreach ($models as $model) {
            $issues = array_merge($issues, $model::getItemsStatus(null, $user, true));
         }
         if (0 < count($issues)) {
            $sections[$header] = $issues;
         }
      }

      return $sections;
   }

   /**
    * Collect overdue or near due activities and control actions for the user.
    *
    * @return array<int, array{level: string, text: string}>
    */
   private function getDueItems(\App\Models\User $user): array
   {
      $limit = date("Y-m-d", strtotime("+7 DAYS"));
      $objects = \App\Models\Activity::where('responsible_user_id', $user->id)->whereNull('completed_at')->where('due', '<', $limit)->orderBy('due')->get()
         ->concat(\App\Models\ControlAction::where('responsible_id', $user->id)->whereNull('finished_at')->where('due', '<', $limit)->orderBy('due')->get());

      $items = [];
      foreach ($objects as $obj) {
         $items[] = [
            'level' => strtotime($obj->due . ' 23:59:59') < time() ? 'danger' : 'info',
            'text' => $obj::getPrettyName() . ' ' . $obj->name . ', ' . __("due") . ' ' . $obj->due,
         ];
      }

      return $items;
   }
}
