<!DOCTYPE html>
<html lang="en">
   <head>
       <meta charset="UTF-8">
       <title>{{ config('ledningssystemet.application_name') }} {{ __("status overview for") }} {{ $user->name }}</title>
       <style type="text/css">
         body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            background-color: #f8f9fa;
            color: #000;
         }
       
         span.introtext {
            
         }
         
         div.status-item {
            position: relative;
            display: block;
            margin-top: 10px;
            text-decoration: none;
            font-size: 12pt;
            color: black;
         }
         
         div.status-item a {
            text-decoration: underline dotted;
            color: #288c98;
         }
         
         div.status-header {
            background-color: #3a3a3a;
            color: white;
            font-size: 14pt;
            padding: 10px;
            margin-top: 20px;
         }
         
         .count {
            width: 30px;
            height: 30px;
            font-size: 15px;
            display: inline-block;
            text-align: center;
            padding: 10px 3px 0px 3px;
            font-weight: 600;
            text-overflow: ellipsis;
         }
         
         .center {
            text-align: center;
            width: 100px;
         }
         
         .bg-info {
            background-color: #779a9e;
            color: white;
         }
         
         .bg-success {
            background-color: #198754;
            color: white;
         }
         
         .bg-warning {
            background-color: #ffcc00;
            color: white;
         }
         
         .bg-danger {
            background-color: #e45316;
            color: white;
         }
       </style>
   </head>
   <body>
      <span class="introtext">
      {{ __("Hi") }}!
         <br /><br />
         {{ __("As a contributing employee of") }} <a href="{{ config('ledningssystemet.application_url') }}" target="_blank">{{ config('ledningssystemet.application_name') }}</a> {{ __("for") }} {{ config('ledningssystemet.company_name') }} {{ __("you receive a status overview according to your own preferences (configurable in the system)") }}.
      </span>
<?php
   $issues = array_merge(
      \App\Models\RequirementSource::getItemsStatus(null, $user, true),
      \App\Models\Process::getItemsStatus(null, $user, true),
      \App\Models\InformationType::getItemsStatus(null, $user, true),
      \App\Models\Asset::getItemsStatus(null, $user, true),
      \App\Models\Customer::getItemsStatus(null, $user, true),
      \App\Models\Supplier::getItemsStatus(null, $user, true),
      \App\Models\Agreement::getItemsStatus(null, $user, true),
      \App\Models\Control::getItemsStatus(null, $user, true),
      \App\Models\ProcessSustainabilityAspect::getItemsStatus(null, $user, true),
      \App\Models\Chemical::getItemsStatus(null, $user, true));
   if(0 < count($issues))
   {
?>
      <div>
         <div class="status-header">{{ __('Inventory') }}</div>
@foreach($issues as $issue)
         <div class="container status-item">
            <span class="count bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
            <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
            <span class="status-item-text">{{ $issue['text'] }}</span>
@endif                  
         </div>
@endforeach
      </div>
<?php
   }
?>

<?php
   $issues = array_merge(
      \App\Models\RiskProject::getItemsStatus(null, $user, true),
      \App\Models\Risk::getItemsStatus(null, $user, true),
      \App\Models\ComplianceEvaluation::getItemsStatus(null, $user, true),
      \App\Models\Finding::getItemsStatus(null, $user, true),
      \App\Models\Incident::getItemsStatus(null, $user, true),
      \App\Models\ControlAction::getItemsStatus(null, $user, true)
   );
   if(0 < count($issues))
   {
?>
      <div>
         <div class="status-header">{{ __('Assess and mitigate') }}</div>
@foreach($issues as $issue)
         <div class="container status-item">
            <span class="count bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
            <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
            <span class="status-item-text">{{ $issue['text'] }}</span>
@endif                  
         </div>
@endforeach
      </div>
<?php
   }
?>

<?php
   $issues = array_merge(
      \App\Models\ProcessPerformanceMetric::getItemsStatus(null, $user, true),
      \App\Models\Objective::getItemsStatus(null, $user, true)
   );
   if(0 < count($issues))
   {
?>
      <div>
         <div class="status-header">{{ __('Measure and improve') }}</div>
@foreach($issues as $issue)
         <div class="container status-item">
            <span class="count bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
            <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
            <span class="status-item-text">{{ $issue['text'] }}</span>
@endif                  
         </div>
@endforeach
      </div>
<?php
   }
?>


<?php
   $issues = array_merge(
      \App\Models\Employee::getItemsStatus(null, $user, true),
      \App\Models\EmployeeRole::getItemsStatus(null, $user, true),
      \App\Models\Competence::getItemsStatus(null, $user, true)
   );
   if(0 < count($issues))
   {
?>
      <div>
         <div class="status-header">{{ __('Employee management') }}</div>
@foreach($issues as $issue)
         <div class="container status-item">
            <span class="count bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
            <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
            <span class="status-item-text">{{ $issue['text'] }}</span>
@endif                  
         </div>
@endforeach
      </div>
<?php
   }
?>

<?php
   $issues = array_merge(
      \App\Models\Activity::getItemsStatus(null, $user, true),
      \App\Models\LibraryDocument::getItemsStatus(null, $user, true)
   );
   if(0 < count($issues))
   {
?>
      <div>
         <div class="status-header">{{ __('Coordination') }}</div>
@foreach($issues as $issue)
         <div class="container status-item">
            <span class="count bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
            <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
            <span class="status-item-text">{{ $issue['text'] }}</span>
@endif                  
         </div>
@endforeach
      </div>
<?php
   }
?>

<?php
   $issues = array_merge(
      \App\Models\User::getItemsStatus(null, $user, true),
      \App\Models\Site::getItemsStatus(null, $user, true),
      \App\Models\Department::getItemsStatus(null, $user, true),
      \App\Models\Role::getItemsStatus(null, $user, true),
      \App\Models\AccessGroup::getItemsStatus(null, $user, true),
   );
   if(0 < count($issues))
   {
?>
      <div>
         <div class="status-header">{{ __('System settings') }}</div>
@foreach($issues as $issue)
         <div class="container status-item">
            <span class="count bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
            <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
            <span class="status-item-text">{{ $issue['text'] }}</span>
@endif                  
         </div>
@endforeach
      </div>
<?php
   }
?>

<?php
   $activities =     \App\Models\Activity::where('responsible_user_id', $user->id)->whereNull('completed_at')->where('due', '<', date("Y-m-d", strtotime("+7 DAYS")))->orderBy('due')->get();
   $controlactions = \App\Models\ControlAction::where('responsible_id', $user->id)->whereNull('finished_at')->where('due', '<', date("Y-m-d", strtotime("+7 DAYS")))->orderBy('due')->get();
   if(0 < (count($activities) + count($controlactions)))
   {
?>
      <div>
         <div class="status-header">{{ __('Overdue or near due activities and control actions') }}</div>
@foreach($activities as $obj)
         <div class="container status-item">
            <span class="count bg-@php echo(strtotime($obj->due.' 23:59:59') < time() ? 'danger' : 'info'); @endphp">&nbsp;</span>
            <span class="status-item-text">{{ $obj::getPrettyName() }} {{ $obj->name }}, {{ __("due") }} {{ $obj->due }}</span>
         </div>
@endforeach
@foreach($controlactions as $obj)
         <div class="container status-item">
            <span class="count bg-@php echo(strtotime($obj->due.' 23:59:59') < time() ? 'danger' : 'info'); @endphp">&nbsp;</span>
            <span class="status-item-text">{{ $obj::getPrettyName() }} {{ $obj->name }}, {{ __("due") }} {{ $obj->due }}</span>
         </div>
@endforeach
      </div>
<?php
   }
?>
   </body>
</html>