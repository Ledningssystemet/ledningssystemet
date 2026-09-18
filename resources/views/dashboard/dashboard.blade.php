@php if(!Auth::user()->canAny(['dashboard.read'])) abort(403); @endphp
<?php
   
   $department = intval(request()->input('department_id', 0));
   if(0 == $department)
      $department = null;
   else
      $department = \App\Models\Department::findOrFail($department)->setAppends([]);
   
?>
@extends('layouts.master')
@section('container')

<style>
   div:has(>.status-container):not(:has(.status-item)){
      display: none;
   }
</style>

<div class="department-selector">
   <div class="d-inline-block department-header col col-12 col-md-8"><h1><?php echo(null == $department ? config('ledningssystemet.company_name') : $department->name); ?></h1></div>
   <div class="d-inline-block department-select form-group mb-3 col col-12 col-md-4">
      <select id="department_id" class="form-control form-select input-rounded" style="font-size: 0.9rem;" onChange="window.location='/dashboard?department_id='+$(this).val();">
         <option value="0" <?php echo((null == $department) ? "selected=\"selected\"" : ""); ?>>{{ __("Show all") }}</option>
   @foreach(\App\Models\Department::orderBy('name')->select(['id','name'])->get()->each->setAppends([]) as $obj)
         <option value="{{ $obj->id }}" <?php echo(((null != $department) && ($obj->id == $department->id)) ? "selected=\"selected\"" : ""); ?>>{{ $obj->name }}</option>
   @endforeach
      </select>
   </div>
</div>
<ul class="nav nav-tabs" id="pageTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="todotab" data-bs-toggle="tab" data-bs-target="#todotabcontent" type="button" role="tab" aria-controls="todotab" aria-selected="true">{{ __("To do") }}</button>
 </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="risktab" data-bs-toggle="tab" data-bs-target="#risktabcontent" type="button" role="tab" aria-controls="risktab" aria-selected="true">{{ __("Risk overview") }}</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="kpitab" data-bs-toggle="tab" data-bs-target="#kpitabcontent" type="button" role="tab" aria-controls="kpitab" aria-selected="true">{{ __("Process metrics") }}</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="systemmetricstab" data-bs-toggle="tab" data-bs-target="#systemmetricstabcontent" type="button" role="tab" aria-controls="systemmetricstab" aria-selected="true">{{ __("System utilization") }}</button>
  </li>
</ul>
<div class="tab-content" id="tabContents">
   <div class="tab-pane fade show active" id="todotabcontent" role="tabpanel" aria-labelledby="todotab">
      <div class="dashboard row">
         <div class="departmentcontainer">
            <div class="container row">
               <div class="col col-12 col-md-6">
                  <div class="department-container-header">{{ __("Inventory") }}</div>
                  <div class="container status-container">
@foreach(array_merge(\App\Models\RequirementSource::getItemsStatus($department), \App\Models\Process::getItemsStatus($department), \App\Models\InformationType::getItemsStatus($department), \App\Models\Asset::getItemsStatus($department), \App\Models\Customer::getItemsStatus($department), \App\Models\Supplier::getItemsStatus($department), \App\Models\Agreement::getItemsStatus($department),\App\Models\Control::getItemsStatus($department), \App\Models\ProcessSustainabilityAspect::getItemsStatus($department), \App\Models\Chemical::getItemsStatus($department)) as $issue)
               <div class="container status-item">
                  <span class="badge bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
                     <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
                     {{ $issue['text'] }}
@endif                  
               </div>
@endforeach
                  </div>
               </div>

               <div class="col col-12 col-md-6">
                  <div class="department-container-header">{{ __("Assess and mitigate") }}</div>
                  <div class="container status-container">
@foreach(array_merge(\App\Models\RiskProject::getItemsStatus($department), \App\Models\Risk::getItemsStatus($department), \App\Models\ComplianceEvaluation::getItemsStatus($department), \App\Models\Finding::getItemsStatus($department), \App\Models\Incident::getItemsStatus($department), \App\Models\ControlAction::getItemsStatus($department)) as $issue)
               <div class="container status-item">
                  <span class="badge bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
                     <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
                     {{ $issue['text'] }}
@endif                  
               </div>
@endforeach
                  </div>
               </div>
               
               <div class="col col-12 col-md-6">
                  <div class="department-container-header">{{ __("Measure and improve") }}</div>
                  <div class="container status-container">
@foreach(array_merge(\App\Models\ProcessPerformanceMetric::getItemsStatus($department), \App\Models\Objective::getItemsStatus($department)) as $issue)
               <div class="container status-item">
                  <span class="badge bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
                     <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
                     {{ $issue['text'] }}
@endif                  
               </div>
@endforeach
                  </div>
               </div>
               
               <div class="col col-12 col-md-6">
                  <div class="department-container-header">{{ __("Employee management") }}</div>
                  <div class="container status-container">
@foreach(array_merge(\App\Models\Employee::getItemsStatus($department), \App\Models\EmployeeRole::getItemsStatus($department), \App\Models\Competence::getItemsStatus($department)) as $issue)
               <div class="container status-item">
                  <span class="badge bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
                     <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
                     {{ $issue['text'] }}
@endif                  
               </div>
@endforeach
                  </div>
               </div>
               
               <div class="col col-12 col-md-6">
                  <div class="department-container-header">{{ __("Coordination") }}</div>
                  <div class="container status-container">
@foreach(array_merge(  \App\Models\Activity::getItemsStatus($department), \App\Models\LibraryDocument::getItemsStatus($department)) as $issue)
               <div class="container status-item">
                  <span class="badge bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
                     <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
                     {{ $issue['text'] }}
@endif                  
               </div>
@endforeach
                  </div>
               </div>

               <div class="col col-12 col-md-6">
                  <div class="department-container-header">{{ __("System settings") }}</div>
                  <div class="container status-container">
@foreach(array_merge(\App\Models\User::getItemsStatus($department), \App\Models\Site::getItemsStatus($department), \App\Models\Department::getItemsStatus($department), \App\Models\Role::getItemsStatus($department), \App\Models\AccessGroup::getItemsStatus($department)) as $issue)
               <div class="container status-item">
                  <span class="badge bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
                     <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
                     {{ $issue['text'] }}
@endif                  
               </div>
@endforeach
                  </div>
               </div>


            </div>
         </div>
      </div>
   </div>
  <div class="tab-pane fade show" id="risktabcontent" role="tabpanel" aria-labelledby="risktab">
      <div class="dashboard row">
         <x-risk-matrix department_id="{{ (null == $department) ? 0 : $department->id }}" />
      </div>
   </div>
   <div class="tab-pane fade show" id="kpitabcontent" role="tabpanel" aria-labelledby="kpitab">
      <div class="dashboard row">
<?php
   $metricheaderCreated = false;

   foreach(((null == $department) ? \App\Models\Process::orderBy('name')->select(['id','name'])->get()->each->setAppends([]) : \App\Models\Process::whereNotNull('department_id')->where('department_id', $department->id)->orderBy('name')->select(['id','name'])->get()->each->setAppends([])) as $process)
   {
      $metrics = $process->int_process_performance_metrics;
      if(0 < count($metrics))
      {
         if(!$metricheaderCreated)
         {
            $metricheaderCreated = true; 
         }
   ?>
      <h3>{{ __("Process") }} {{ $process->name }}</h3>
   @foreach($metrics as $metric)
      <div class="card kpicontainer col-12 col-md-4" data-kpi-id="{{ $process->id }}-{{ $metric->id }}">
         <div class="card-header">{{ $metric->name }}</div>
   <?php
   $data = $metric->int_process_performance_metric_reports()->orderBy('reporting_date_at')->take(-20)->get();
   ?>   
   @if(0 == count($data))
         <div class="card-body">
         {{ __("No reports available") }}
         </div>
      </div>
   @else
   @if($metric->quantitative)   
         <div class="card-body quantitative">
            <canvas id="kpi-{{ $process->id}}-{{ $metric->id }}"></canvas>
            <div class="kpivalue">{{ number_format($data->last()->calculatedvalue, $metric->precision, ',', ' ') }} {{ $metric->unit }}</div>
         </div>
         <div class="card-footer">
            <div class="kpilastdate">{{ __("Last report") }} {{ $data->last()->reporting_date_at}}</div>
         </div>
      </div>
      <script>
         $(function(){
            var chartArea = $('.kpicontainer[data-kpi-id="{{ $process->id }}-{{ $metric->id }}"] canvas');       
            showKpi(chartArea, [
   @foreach($data as $dataitem)
               { date: '{{ $dataitem->reporting_date_at }}', value: {{ number_format($dataitem->calculatedvalue, $metric->precision, '.','') }} },
   @endforeach         
            ], '', true);
         });
      
      </script>
   @else
         <div class="card-body non-quantitative">
            <div class="kpivalue">{{ $data->last()->comment }}</div>
         </div>
         <div class="card-footer">
            <div class="kpilastdate">{{ __("Last report") }} {{ $data->last()->reporting_date_at}}</div>
         </div>
      </div>
   @endif
   @endif
      
   @endforeach

   <?php      
      }
   }
   ?>
      </div>
   </div>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>

      $(function(){

         google.charts.load('current', {'packages':['corechart']});
         google.charts.setOnLoadCallback(drawPerformanceCharts);

         function drawPerformanceCharts() {
           var data = google.visualization.arrayToDataTable([
           [
              '{{ __("Month") }}', 
              '{{ __("Findings") }}',
              '{{ __("Risks") }}',
@if(!$department)      
              '{{ __("Activities") }}',
              '{{ __("Control actions") }}' 
@endif
            ],
<?php
   $findings = DB::table('findings')
      ->select(DB::raw('count(*) as itemcount, SUBSTR(created_at, 1, 7) AS month'))
      ->where('created_at', '>=', date("Y-m-d", strtotime("FIRST DAY OF -13 MONTHS"))." 00:00:00")
      ->when($department, function($query, $department){
         $query->where('department_id', $department->id);
      })
      ->groupBy('month')
      ->get()
      ->keyBy('month')
      ->toArray();

   $risks = DB::table('risks')
      ->select(DB::raw('count(*) as itemcount, SUBSTR(created_at, 1, 7) AS month'))
      ->where('created_at', '>=', date("Y-m-d", strtotime("FIRST DAY OF -13 MONTHS"))." 00:00:00")
      ->when($department, function($query, $department){
         $query->where('department_id', $department->id);
      })
      ->groupBy('month')
      ->get()
      ->keyBy('month')
      ->toArray();
   
   $activities = null;
   $control_actions = null;
   
if(!$department)      
{
   $activities = DB::table('activities')
      ->select(DB::raw('count(*) as itemcount, SUBSTR(created_at, 1, 7) AS month'))
      ->where('created_at', '>=', date("Y-m-d", strtotime("FIRST DAY OF -13 MONTHS"))." 00:00:00")
      ->groupBy('month')
      ->get()
      ->keyBy('month')
      ->toArray();
      
   $control_actions = DB::table('control_actions')
      ->select(DB::raw('count(*) as itemcount, SUBSTR(created_at, 1, 7) AS month'))
      ->where('created_at', '>=', date("Y-m-d", strtotime("FIRST DAY OF -13 MONTHS"))." 00:00:00")
      ->groupBy('month')
      ->get()
      ->keyBy('month')
      ->toArray();
}

for($i = -12; $i <= 0; $i++)
{
?>   
            [   '{{ date("Y-m", strtotime($i." MONTHS")) }}',
                {{ array_key_exists(date("Y-m", strtotime($i." MONTHS")), $findings) ? $findings[date("Y-m", strtotime($i." MONTHS"))]->itemcount : 0 }},
                {{ array_key_exists(date("Y-m", strtotime($i." MONTHS")), $risks) ? $risks[date("Y-m", strtotime($i." MONTHS"))]->itemcount : 0 }},
@if(!$department)      
                {{ array_key_exists(date("Y-m", strtotime($i." MONTHS")), $activities) ? $activities[date("Y-m", strtotime($i." MONTHS"))]->itemcount : 0 }},
                {{ array_key_exists(date("Y-m", strtotime($i." MONTHS")), $control_actions) ? $control_actions[date("Y-m", strtotime($i." MONTHS"))]->itemcount : 0 }},
@endif                
            ],
<?php
}
?>
         ]);

         var options = {
           title: '{{ __("System activity by created objects") }}',
           curveType: 'function',
           width: 800,
           height: 400,
           chartArea: { left: 50, width: 500 },
           hAxis: {
              slantedText: true,
           }
         };

         var chart = new google.visualization.LineChart(document.getElementById('activitycontainer'));

         chart.draw(data, options);

         data = google.visualization.arrayToDataTable([
          ['ID', '{{ __("Avg days for approval") }}', '{{ __("Avg risk level") }}', '{{ __("Department") }}', '{{ __("Number of risks") }}'],
<?php
$hasData = false;
foreach(App\Models\Department::get() as $dept)
{
   if($department && $department->id && ($dept->id != $department->id))
      continue;
   
   $riskcount =  App\Models\Risk::where('department_id', $dept->id)->whereNotNull('assessed_at')->count();
   if(!$riskcount)
      continue;
   $depdata = App\Models\Risk
      ::whereNotNull('risks.assessed_at')
      ->where('risks.department_id', $dept->id)
      ->leftJoin('risk_level_mappings', function($join){
         $join
            ->on('risks.probability_id', '=', 'risk_level_mappings.probability_level_id')
            ->on('risks.consequence_id', '=', 'risk_level_mappings.consequence_level_id');
      })
      ->leftJoin('risk_levels', 'risk_level_mappings.risk_level_id', '=', 'risk_levels.id')
      ->select([
         DB::raw('ROUND(AVG(DATEDIFF(risks.assessed_at,risks.created_at))) as avgtime'),
         DB::raw('ROUND(AVG(risk_levels.ordinal),1) AS riskordinal'),
      ])->first();
   $hasData = true;

?>
             ['',    {{ $depdata->avgtime }},              {{ $depdata->riskordinal }},      @php echo(json_encode($dept->name)); @endphp,  {{ $riskcount }}],
<?php
}
?>
          ]);

@if($hasData)
            var options = {
              title: '{{ __("Risk management by department") }}',
              hAxis: {title: '{{ __("Avg days for approval") }}'},
              vAxis: {title: '{{ __("Avg risk level") }}'},
              bubble: {textStyle: {fontSize: 11}},
              width: 800,
              height: 400,
              chartArea: { left: 50, width: 500 },
             };
            
            var bubblechart = new google.visualization.BubbleChart(document.getElementById('riskmanagementcontainer'));
            bubblechart.draw(data, options);         
@endif

         data = google.visualization.arrayToDataTable([
          ['ID', '{{ __("Avg days for approval") }}', '{{ __("Avg risk level") }}', '{{ __("Risk owner") }}', '{{ __("Number of risks") }}'],
<?php
$hasData = false;
foreach(App\Models\Risk
   ::whereNotNull('assessed_at')
   ->when($department, function($query, $department) {
      $query->where('department_id', $department->id); 
   })
   ->whereNotNull('riskowner_id')
   ->select('riskowner_id')
   ->distinct()
   ->pluck('riskowner_id')
   ->toArray() as $roid)
{
   $riskowner = App\Models\User::findOrFail($roid);
   $riskcount =  App\Models\Risk::where('riskowner_id', $riskowner->id)->whereNotNull('assessed_at')->count();
   
   if(!$riskcount)
      continue;
   
   $depdata = App\Models\Risk
      ::whereNotNull('risks.assessed_at')
      ->where('risks.riskowner_id', $riskowner->id)
      ->leftJoin('risk_level_mappings', function($join){
         $join
            ->on('risks.probability_id', '=', 'risk_level_mappings.probability_level_id')
            ->on('risks.consequence_id', '=', 'risk_level_mappings.consequence_level_id');
      })
      ->leftJoin('risk_levels', 'risk_level_mappings.risk_level_id', '=', 'risk_levels.id')
      ->select([
         DB::raw('ROUND(AVG(DATEDIFF(risks.assessed_at,risks.created_at))) as avgtime'),
         DB::raw('ROUND(AVG(risk_levels.ordinal),1) AS riskordinal'),
      ])->first();
   $hasData = true;

?>
             ['',    {{ $depdata->avgtime }},              {{ $depdata->riskordinal }},      @php echo(json_encode($riskowner->name)); @endphp,  {{ $riskcount }}],
<?php
}
?>
          ]);

@if($hasData)
            var options = {
              title: '{{ __("Risk management by risk owner") }}',
              hAxis: {title: '{{ __("Avg days for approval") }}'},
              vAxis: {title: '{{ __("Avg risk level") }}'},
              bubble: {textStyle: {fontSize: 11}},
              width: 800,
              height: 400,
              chartArea: { left: 50, width: 500 },
             };
            
            var bubblechart = new google.visualization.BubbleChart(document.getElementById('riskownercontainer'));
            bubblechart.draw(data, options);         
@endif



         }
      });
   </script>

   <div class="tab-pane fade show" id="systemmetricstabcontent" role="tabpanel" aria-labelledby="systemmetricstab">
      <div id="activitycontainer" style="width: 800px; height: 400px;"></div>
      <div id="riskmanagementcontainer" style="width: 800px; height: 400px;"></div>
      <div id="riskownercontainer" style="width: 800px; height: 400px;"></div>
   </div>
</div>


@endsection
