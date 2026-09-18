<?php
   $user = auth()->user();
   $mysubordinates = auth()->user()->int_reporting_users();
   $usersubordinates = null;
   if(request()->has('user_id') && (request()->input('user_id') != auth()->user()->id))
   {
      if(!array_key_exists(request()->input('user_id'), $mysubordinates))
         abort(403);
      
      $user = \App\Models\User::where('id', request()->input('user_id'))->first();
      $usersubordinates = $user->int_reporting_users();
   }
   else
      $usersubordinates = $mysubordinates;

   if(null == $user)
      abort(404);
   
   
   $qualificationTabClass = '';
   $competenceTabClass = '';
   
   foreach(\App\Models\Me::getItemsStatus(null, $user) as $obj)
   {
      switch($obj['area'])
      {
         case 'qualification':
            if('' == $qualificationTabClass ||
               (('warning' == $qualificationTabClass) && ('danger' == $obj['level'])) ||
               ('info' == $qualificationTabClass)           
            )
               $qualificationTabClass = $obj['level'];
            break;
         case 'competence':
            if('' == $competenceTabClass ||
               (('warning' == $competenceTabClass) && ('danger' == $obj['level'])) ||
               ('info' == $competenceTabClass)           
            )
               $competenceTabClass = $obj['level'];
            break;
      }
   }
   
?>


@extends('layouts.master')
@section('container')
<script>
$(function(){

});   
</script>
<div id="me-page">
   <h1>{{ $user->name }}</h1>
   <ul class="nav nav-tabs" id="pageTabs" role="tablist">
     <li class="nav-item" role="presentation">
       <button class="nav-link active" id="generaltab" data-bs-toggle="tab" data-bs-target="#generaltabcontent" type="button" role="tab" aria-controls="generaltab" aria-selected="true">{{ __("General information") }}</button>
     </li>
     <li class="nav-item" role="presentation">
       <button class="nav-link" id="rolestab" data-bs-toggle="tab" data-bs-target="#rolestabcontent" type="button" role="tab" aria-controls="rolestab" aria-selected="true">{{ __("Roles and tasks") }}</button>
     </li>
     <li class="nav-item" role="presentation">
       <button class="nav-link {{ $qualificationTabClass }}" id="qualificationstab" data-bs-toggle="tab" data-bs-target="#qualificationstabcontent" type="button" role="tab" aria-controls="qualificationstab" aria-selected="true">{{ __("Qualifications") }}</button>
     </li>
     <li class="nav-item" role="presentation">
       <button class="nav-link {{ $competenceTabClass }}" id="competencestab" data-bs-toggle="tab" data-bs-target="#competencestabcontent" type="button" role="tab" aria-controls="competencestab" aria-selected="true">{{ __("Competences") }}</button>
     </li>
     <li class="nav-item" role="presentation">
       <button class="nav-link" id="responsibilitiestab" data-bs-toggle="tab" data-bs-target="#responsibilitiestabcontent" type="button" role="tab" aria-controls="responsibilitiestab" aria-selected="true">{{ __("Assigned responsibilities") }}</button>
     </li>
   </ul>
   <div class="tab-content" id="tabContents">
     <div class="tab-pane fade show active container row" id="generaltabcontent" role="tabpanel" aria-labelledby="generaltab">
         <div class="col col-12 col-md-4 infocontainer">
            <div class="info-header">{{ __("Title") }}</div>
            <div class="info-value">{{ $user->title }}</div>
         </div>
         <div class="col col-12 col-md-4 infocontainer">
            <div class="info-header">{{ __("Departments") }}</div>
            <div class="info-value">@php echo(implode("<br />", $user->int_departments()->pluck('name')->toArray())); @endphp</div>
         </div>
         <div class="col col-12 col-md-4 infocontainer">
            <div class="info-header">{{ __("Manager") }}</div>
            <div class="info-value">
@if($user->int_manager)
@if(($user->int_manager->id == auth()->user()->id) || array_key_exists($user->int_manager->id, $mysubordinates))
         <a class="directreport" href="/user/me?user_id={{ $user->int_manager->id }}">{{ $user->int_manager->name }}</a>
@else
         <span class="directreport">{{ $user->int_manager->name }}</span>
@endif
@else
               <span>{{ __("None") }}</span>
@endif

            </div>
         </div>
@if(count($usersubordinates))     
         <div class="col col-12 col-md-6 infocontainer">
            <div class="info-header">{{ __("Direct reports") }}</div>
@foreach(\App\Models\User::where('manager_user_id', $user->id)->where('enabled', true)->get()->each->setAppends([]) as $obj)
@if(array_key_exists($obj->id, $mysubordinates))
            <a class="directreport" href="/user/me?user_id={{ $obj->id }}">{{ $obj->name }}</a>
@else
            <span class="directreport">{{ $obj->name }}</span>
@endif
@endforeach
         </div>   
@endif
     </div>   
     <div class="tab-pane fade container row" id="rolestabcontent" role="tabpanel" aria-labelledby="rolestab">
@foreach($user->int_roles()->orderBy('name')->get()->each->setAppends([]) as $role)
         <h3>{{ __("Role") }} {{ $role->name }}</h3>
         <div class="col col-12 roledescription">{{ $role->description }}</div>
         <div class="col col-12 infocontainer">
            <div class="info-header">{{ __("Authorities") }}</div>
            <div class="info-value">{{ $role->authorities }}</div>
         </div>
<?php
$processaccountability = [];
$processresponsibility = [];

foreach($role->int_process_activities_accountable()->leftJoin('processes', 'processes.id', '=', 'process_activities.process_id')->select('process_activities.*', 'processes.name as process_name', 'processes.id as process_id')->orderBy('process_name')->orderBy('name')->get()->each->setAppends([]) as $obj)
{
   if(!array_key_exists($obj->process_id, $processaccountability))
      $processaccountability[$obj->process_id] = [];
   
   $processaccountability[$obj->process_id][] = $obj;
}

foreach($role->int_process_activities_responsible()->leftJoin('processes', 'processes.id', '=', 'process_activities.process_id')->select('process_activities.*', 'processes.name as process_name', 'processes.id as process_id')->orderBy('process_name')->orderBy('name')->get()->each->setAppends([]) as $obj)
{
   if(!array_key_exists($obj->process_id, $processresponsibility))
      $processresponsibility[$obj->process_id] = [];
   
   $processresponsibility[$obj->process_id][] = $obj;
}
?>               
         <div class="col col-12 infocontainer">
            <div class="info-header">{{ __("Accountable for") }}</div>
            <div class="info-value processactivitycontainer accordion">
@foreach(\App\Models\Process::orderBy('name')->get()->each->setAppends([]) as $process)
@if(array_key_exists($process->id, $processaccountability))
               <div class="accordion-item">
                  <h2 class="accordion-header">
                     <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pacollapse-{{ $process->id }}" aria-expanded="false" aria-controls="prcollapse-{{ $process->id }}">
                        {{ $process->name }}
                     </button>
                  </h2>
                  <div id="pacollapse-{{ $process->id }}" class="accordion-collapse collapse collapsed">
@foreach($processaccountability[$process->id] as $pa)
                     <a href="/?process={{ $process->id }}" target="_blank">{{ $pa->name }}</a>
@endforeach            
                  
                  </div>
               </div>
@endif
@endforeach            
            </div>
         </div>
         <div class="col col-12 infocontainer">
            <div class="info-header">{{ __("Responsible for") }}</div>
            <div class="info-value processactivitycontainer accordion">
@foreach(\App\Models\Process::orderBy('name')->get()->each->setAppends([]) as $process)
@if(array_key_exists($process->id, $processresponsibility))
               <div class="accordion-item">
                  <h2 class="accordion-header">
                     <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#prcollapse-{{ $process->id }}" aria-expanded="false" aria-controls="prcollapse-{{ $process->id }}">
                        {{ $process->name }}
                     </button>
                  </h2>
                  <div id="prcollapse-{{ $process->id }}" class="accordion-collapse collapse collapsed">
@foreach($processresponsibility[$process->id] as $pa)
                     <a href="/?process={{ $process->id }}" target="_blank">{{ $pa->name }}</a>
@endforeach            
                  
                  </div>
               </div>
@endif
@endforeach            
            </div>
         </div>
@endforeach            
     </div>
     <div class="tab-pane fade container row" id="qualificationstabcontent" role="tabpanel" aria-labelledby="qualificationstab">
<?php
$qualifications = $user->int_qualifications()->get()->each->setAppends([]);
?>
@if(0 < count($qualifications))
      <h2>{{ __("My qualifications") }}</h2>
@foreach($qualifications as $obj)
         <div class="qualification">
            <div class="infocontainer">
               <div class="info-header">{{ $obj->name }}</div>
@if($obj->finished_at)
               <div class="info-value info"><span>{{ __("Achieved") }} {{ $obj->finished_at }}</span></div>
@else
               <div class="info-value danger"><span>{{ __("Not achieved") }}</span></div>
@endif
@if($obj->planned_at)
               <div class="info-value info"><span>{{ __("Planned") }} {{ $obj->planned_at }}</span></div>
@endif
@if($obj->expires_at)
               <div class="info-value<?php if(strtotime($obj->expires_at) < time()) echo(" danger"); else if (strtotime($obj->expires_at) < strtotime("+1 MONTHS")) echo(" warning");else echo(" info"); ?>"><span>{{ __("Expires") }} {{ $obj->expires_at}}</span></div>
@endif
            </div>
         </div>
@endforeach  
@endif
<?php
$missingQualifications = $user->int_mandatory_qualifications()->whereNull('finished_at')->get()->each->setAppends([]);
?>
@if(0 < count($missingQualifications))
      <h2>{{ __("Missing qualifications") }}</h2>
@foreach($missingQualifications as $obj)
         <div class="qualification">
            <div class="infocontainer">
               <div class="info-header">{{ $obj->name }}</div>
            </div>
         </div>
@endforeach  
@endif


     </div>   
     <div class="tab-pane fade container row" id="competencestabcontent" role="tabpanel" aria-labelledby="competencestab">
         <div id="competenceTable"></div>
     </div>   
     <div class="tab-pane fade container row" id="responsibilitiestabcontent" role="tabpanel" aria-labelledby="responsibilitiestab">
<?php
   $processes = \App\Models\Process::where('responsible_user_id', $user->id)->orderBy('name')->get()->each->setAppends([]);
   $informationtype = \App\Models\InformationType::where('responsible_user_id', $user->id)->orderBy('name')->get()->each->setAppends([]);
   $assets = \App\Models\Asset::where('responsible_user_id', $user->id)->orderBy('name')->get()->each->setAppends([]);
   $customer = \App\Models\Customer::where('responsible_user_id', $user->id)->orderBy('name')->get()->each->setAppends([]);
   $supplier = \App\Models\Supplier::where('responsible_user_id', $user->id)->orderBy('name')->get()->each->setAppends([]);
   $controls = \App\Models\Control::whereNull('not_applicable_at')->where('responsible_user_id', $user->id)->orderBy('name')->get()->each->setAppends([]);
?>
@if(0 == (count($processes)+count($informationtype)+count($assets)+count($customer)+count($supplier)+count($controls)))
   {{ __("No direct assigned responsibilities for processes, information types, assets, customers, supppliers or controls") }}
@else

@if(0 < count($processes))
      <div class="col col-12 col-md-6 infocontainer">
         <h2>{{ __("Processes") }}</h2>
@foreach($processes as $obj)
         <div><a href="/inventory/processes?jtId[processeslist]={{ $obj->id }}" target="_blank">{{ $obj->name }}</a></div>
@endforeach
      </div>
@endif

@if(0 < count($customer))
      <div class="col col-12 col-md-6 infocontainer">
         <h2>{{ __("Customers") }}</h2>
@foreach($customer as $obj)
         <div><a href="/inventory/customers?jtId[customerslist]={{ $obj->id }}" target="_blank">{{ $obj->name }}</a></div>
@endforeach
      </div>
@endif

@if(!config('ledningssystemet.disable_supplier'))
@if(0 < count($supplier))
      <div class="col col-12 col-md-6 infocontainer">
         <h2>{{ __("Suppliers") }}</h2>
@foreach($supplier as $obj)
         <div><a href="/inventory/suppliers?jtId[supplierslist]={{ $obj->id }}" target="_blank">{{ $obj->name }}</a></div>
@endforeach
      </div>
@endif
@endif

@if(0 < count($informationtype))
      <div class="col col-12 col-md-6 infocontainer">
         <h2>{{ __("Information types") }}</h2>
@foreach($informationtype as $obj)
         <div><a href="/inventory/informationtypes?jtId[informationtypelist]={{ $obj->id }}" target="_blank">{{ $obj->name }}</a></div>
@endforeach
      </div>
@endif

@if(0 < count($assets))
      <div class="col col-12 col-md-6 infocontainer">
         <h2>{{ __("Assets") }}</h2>
@foreach($assets as $obj)
         <div><a href="/inventory/assets?jtId[assetslist]={{ $obj->id }}" target="_blank">{{ $obj->name }}</a></div>
@endforeach
      </div>
@endif

@if(0 < count($controls))
      <div class="col col-12 col-md-6 infocontainer">
         <h2>{{ __("Controls") }}</h2>
@foreach($controls as $obj)
         <div><a href="/inventory/controls?jtId[controlstable]={{ $obj->id }}" target="_blank">{{ $obj->name }}</a></div>
@endforeach
      </div>
@endif

@endif
     </div>   
   </div>
</div>

<script>
$(function(){
   $('div.danger').each(function(){
      $(this).append($('<span class="text-danger material-symbols-rounded">warning</span>'));
   });
   
   $('div.warning').each(function(){
      $(this).append($('<span class="text-danger material-symbols-rounded">info</span>'));
   });
   
   $('div.info').each(function(){
      $(this).append($('<span class="text-info material-symbols-rounded">check</span>'));
   });
   
   $('#competenceTable').jtable({
      title: '&nbsp;',
      tableId: 'competencesstable',
      searchfield: true,
     filter: {
         usermandatoryonly: {
            type: 'checkbox',
            value: '1',
            default: true,
            text: '{{ __('Show mandatory competences only') }}',
         },
      },
      paging: true,
      bootstrap: true,
      actions: {
@if(Auth::user()->can('index', \App\Models\Competence::class))         
         listAction: '/api/v1/items/Competence?user_id={{ $user->id }}',
@endif         

      },
      fields: {
         id: {
            key: true,
            list: false,
         },
         name: {
            title: '',
            list: true,
            header: true,
         },
         description: {
            title: '{{ __("Description") }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         acceptable_level: {
            title: '{{ __("Acceptable level") }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         desired_level: {
            title: '{{ __("Desired level") }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         hr0: {
            title: '',
            list: true,
            display: function(data){
               return '<hr />';
            }
         },
         evaluated: {
            title: '{{ __("Evaluated") }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         achieved_level: {
            title: '{{ __("Actual level") }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: function(data){

               return '/api/v1/items/CompetenceLevel?competence_id='+data.record.id;
            }
         },
         note: {
            title: '{{ __("Evaluation notes") }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
      },
      rowLoaded: function(event, data) {
         if(data.record.evaluation_required && !data.record.evaluated)
            $('.jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="evaluated"] .jtable-cell-content').addClass('text-danger');

         if(!data.record.competence_acceptable)
         {
            $('.jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="acceptable_level"] .jtable-cell-content').addClass('text-danger');
            $('.jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="achieved_level"] .jtable-cell-content').addClass('text-danger');
         }
         
         if(!data.record.competence_asdesired)
         {
            $('.jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="desired_level"] .jtable-cell-content').addClass('text-warning');
            $('.jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="achieved_level"] .jtable-cell-content').addClass('text-warning');
         }
      }
   });
   
   $('#competenceTable').jtable('load');
   
});
</script>   

@endsection
