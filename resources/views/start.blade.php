<?php
// Catch process data load action
if(request()->has('action'))
{
   if(('getProcessData' == request()->input('action')))
   {
      try{
          $process = \App\Models\Process::find(request()->input('id'))->setAppends([]);
          if(null == $process)
             abort(404);

          $retval = [
             'id' => $process->id,
             'name' => $process->name,
             'description' => $process->description,
             'xml' => $process->publishedbpmn,
             'viewbox' => $process->getViewbox(),
          ];

          foreach($process->int_assets()->get()->each->setAppends([]) as $obj)
          {
             if(!array_key_exists('assets', $retval))
                $retval['assets'] = [];

             $retval['assets'][] = array(
                'id' => $obj->id,
                'name' => $obj->name,
                'description' => $obj->description,
             );
          }

          foreach($process->int_process_activities()->get()->each->setAppends([]) as $obj)
          {
             if(!array_key_exists('activities', $retval))
                $retval['activities'] = [];

             $retval['activities'][] = array(
                'id' => $obj->id,
                'name' => $obj->name,
                'description' => $obj->description,
             );

             foreach($obj->int_information_types()->get()->each->setAppends([]) as $it)
             {
                if(!array_key_exists('informationtypes', $retval))
                   $retval['informationtypes'] = [];

                $found = false;
                foreach($retval['informationtypes'] as $eit)
                {
                   if($eit['id'] == $it->id)
                   {
                      $found = true;
                      break;
                   }
                }

                if(!$found)
                {
                   $retval['informationtypes'][] = array(
                      'id' => $it->id,
                      'name' => $it->name,
                      'description' => $it->description,
                   );
                }
             }
          }

          foreach($process->int_library_documents()->where('contentlength', '>', 0)->orderBy('name')->get()->each->setAppends([]) as $obj)
          {
             if(!array_key_exists('librarydocuments', $retval))
                $retval['librarydocuments'] = [];

             $retval['librarydocuments'][] = array(
                'id' => $obj->id,
                'name' => $obj->name,
                'description' => $obj->description,
             );
          }

          foreach($process->int_process_hrefs()->orderBy('name')->get()->each->setAppends([]) as $obj)
          {
             if(!array_key_exists('hrefs', $retval))
                $retval['hrefs'] = [];

             $retval['hrefs'][] = array(
                'id' => $obj->id,
                'name' => $obj->name,
                'description' => $obj->description,
                'url' => $obj->url,
                'blank' => $obj->blank,
             );
          }

          die(json_encode($retval));
      }
        catch(\Exception $e)
        {
             abort(400, __("Unfortunately the process chart could not be visualized yet. Please contact the process responsible and ask for them to re-publish the process."));
        }
   }
   else if('getObjectives' == request()->input('action'))
   {
      if(!auth()->user()->can('showobjectivestatus.read'))
         return abort(403);

      die(json_encode(\App\Models\Objective::whereNull('department_id')->orderBy('name')->get()));
   }
   else
      abort(404);
}
?>

@extends('layouts.master')
@section('container')

<?php
// Calculate process to display
$process = (request()->has('process')) ? \App\Models\Process::find(request()->input('process')) : \App\Models\Process::where('isstartprocess',1)->orderBy('updated_at', 'desc')->first();
?>
<script>
      var processes = [
   @foreach(\App\Models\Process::orderBy('name')->get()->each->setAppends([]) as $obj)
      { id: {{ $obj->id }}, name: '{{ $obj->name }}', text: '{{ $obj->name }}', selected: {{ ((null != $process) && ($process->id == $obj->id)) ? "true" : "false" }} },
   @endforeach
      ];
      
      var activities = null;
      var informationtypes = null;
      var assets = null;
      var documents = null;
      var links = null;
      var bpmnViewer = null;
      
      function loadProcess(id)
      {
         var pContainer = $('div#processchart');
         $('div#documentlist').empty();
         $('div#linklist').empty();
         pContainer.empty();
         $('div#processchart').css({'width': 'auto', 'height': 'auto'});
         
         if(id <= 0)
         {
            $('a#editProcessChart').addClass('d-none');
            return;
         }
         else
            $('a#editProcessChart').removeClass('d-none');

         
         ajaxGet("{!! Request::url().'?action=getProcessData&id=' !!}"+id, function(data){
            activities = data.activities;
            informationtypes = data.informationtypes;
            assets = data.assets;
            documents = data.librarydocuments;
            links = data.hrefs;
            
            if (!((null == data.viewbox.left) ||
               (null == data.viewbox.right) ||
               (null == data.viewbox.top) ||
               (null == data.viewbox.bottom)))
            {
               pContainer.width(data.viewbox.right-data.viewbox.left);
               pContainer.height(data.viewbox.bottom-data.viewbox.top);
               
               bpmnViewer = $('div#processchart').bpmnview();
               $('div#processchart').bpmnview('load', data.xml);
               $('div#processchart').bpmnview('click', function(event){
                  switch(event.element.type)
                  {
                     case 'bpmn:SubProcess':
                        processes.forEach((obj) => {
                           if(obj.name == event.element.businessObject.name)
                           {
                              $('#processSelector').val(obj.id).trigger('change');
                           }
                        });
                        break;
                        
                     case 'bpmn:DataObjectReference':
                        informationtypes.forEach((obj) => {
                           if(obj.name == event.element.businessObject.name)
                           {
                              if(obj.description)
                                 showDialog(event.element.businessObject.name, $('<span />').addClass('processchart_info').text(obj.description));
                           }
                        });
                        break;
                     
                     case 'bpmn:DataStoreReference':
                        assets.forEach((obj) => {
                           if(obj.name == event.element.businessObject.name)
                           {
                              if(obj.description)
                                 showDialog(event.element.businessObject.name, $('<span />').addClass('processchart_info').text(obj.description));
                           }
                        });
                        break;
                     case 'bpmn:Task':
                        activities.forEach((obj) => {
                           if(obj.name == event.element.businessObject.name)
                           {
                              if(obj.description)
                                 showDialog(event.element.businessObject.name, $('<span />').addClass('processchart_info').text(obj.description));
                           }
                        });
                        break;
                     default:
                        break;
                  }
               });
               
               var eventBus = bpmnViewer.get('eventBus');
               eventBus.on('import.render.complete', function(event){
                  // Additional styling for objects with info
                  if(activities){
                     Object.values(activities).forEach((obj) => {
                        var svgelements = $('#processchart g.djs-visual.data-type-task[data-name="'+obj.name+'"]');
                        if(obj.description)
                        {
                           $('<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" x="2" y="2" fill="#5f6368"><path d="M440-280h80v-240h-80v240Zm40-320q17 0 28.5-11.5T520-640q0-17-11.5-28.5T480-680q-17 0-28.5 11.5T440-640q0 17 11.5 28.5T480-600Zm0 520q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>')
                              .appendTo(svgelements);
                        }
                     });
                  }
                  
                  if(informationtypes){
                     Object.values(informationtypes).forEach((obj) => {
                        var svgelements = $('#processchart g.djs-visual.data-type-dataobjectreference[data-name="'+obj.name+'"]');
                        if(obj.description)
                        {
                           $('<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" x="8" y="13" fill="#5f6368"><path d="M440-280h80v-240h-80v240Zm40-320q17 0 28.5-11.5T520-640q0-17-11.5-28.5T480-680q-17 0-28.5 11.5T440-640q0 17 11.5 28.5T480-600Zm0 520q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>')
                              .appendTo(svgelements);
                        }
                     });
                  }
                  
                 if(assets){
                   Object.values(assets).forEach((obj) => {
                     var svgelements = $('#processchart g.djs-visual.data-type-datastorereference[data-name="'+obj.name+'"]');
                     if(obj.description)
                     {
                        $('<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" x="15" y="28" fill="#5f6368"><path d="M440-280h80v-240h-80v240Zm40-320q17 0 28.5-11.5T520-640q0-17-11.5-28.5T480-680q-17 0-28.5 11.5T440-640q0 17 11.5 28.5T480-600Zm0 520q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>')
                           .appendTo(svgelements);
                     }
                  });
                 }
                 
               });
            }
            
            if(documents && (documents.length > 0))
            {
               $('div#documentlist').append($('<h4 />')
                  .text('{{ __("Documents")}}'));
                  
               documents.forEach((obj) => {
                  $('div#documentlist').append($('<div><span class="material-symbols-rounded">description</span></div>')
                     .append($('<a />')
                        .attr('href', '/api/v1/LibraryDocument/'+obj.id+'/download')
                        .addClass('documentlink')
                        .prop('title', (null != obj.description ? obj.description : ''))
                        .text(obj.name)
                     )
                  );
               });
            }

            if(links && (links.length > 0))
            {
               $('div#linklist').append($('<h4 />')
                  .text('{{ __("Links")}}'));
                  
               links.forEach((obj) => {
                  $('div#linklist').append($('<div><span class="material-symbols-rounded">link</span></div>')
                     .append($('<a />')
                        .attr('href', obj.url)
                        .attr('target', obj.blank ? '_blank' : '_self')
                        .addClass('externallink')
                        .prop('title', (null != obj.description ? obj.description : ''))
                        .text(obj.name)
                     )
                  );
               });
            }
            
            $('div.processdescription').html(data.description);
         });
      }
      
      var hideDetails = null;

      function toggleDetails() {
         showDetails = $('#showdetailsbutton').prop('checked');
         setCookieValue('showprocesschartdetails', showDetails);

         if(showDetails)
            $('#processchart').removeClass('hidedetails');
         else
            $('#processchart').addClass('hidedetails');
      }

      $(function(){
         $('#showdetailsbutton').prop('checked', getCookieValue('showprocesschartdetails'));
         toggleDetails();
      });


      $(function(){
@if(null != $process)
         loadProcess({{$process->id}});
@endif
@if(auth()->user()->can('showobjectivestatus.read'))
      ajaxCall({
         url: '{!! Request::url() !!}?action=getObjectives',
         method: 'GET',
         global:false,
         success: function(data){
            var objectivesdata = Object.values(data);
            
            objectivesdata.forEach((objective) => {
               var classname = 'bg-status-unknown';
               var statustext = '{{ __("Unknown") }}';

               if(objective.ontarget)
               {
                  classname = 'bg-status-ontarget';
                  statustext = '{{ __("On target") }}';
               }
               else if(objective.acceptable)
               {
                  classname = 'bg-status-acceptable';
                  statustext = '{{ __("Acceptable") }}';
               }
               else if(objective.unacceptable)
               {
                  classname = 'bg-status-unacceptable';
                  statustext = '{{ __("Unacceptable") }}';
               }

               var container = $('<div />')
                  .addClass('objective')
                  .append($('<div />')
                     .addClass('objective-icon-container')
                     .append($('<span />')
                        .addClass('badge')
                        .addClass(classname)
                     )
                  )
                  .append($('<div />')
                     .addClass('objective-text')
                     .text(objective.name))
                  .appendTo($('#objectivescontainer'));
                  
               var infocontainer = $('<div />')
                  .addClass('objectivedescription');
                  
               infocontainer.append($('<div />')
                  .addClass('statustext')
                  .text('{{ __("Status") }}: ' + statustext));
                  
                  
               infocontainer.append($('<div />')
                  .addClass('due')
                  .text('{{ __("Due") }}: ' + objective.due));
                  
               infocontainer.append($('<div />')
                  .addClass('description')
                  .text(objective.description));
                  
@if(auth()->user()->can('index', \App\Models\Objective::class))
               infocontainer.append($('<a />')
                  .addClass('link')
                  .prop('href', '/measure/objectives?jtId[objectiveslist]='+objective.id)
                  .text('{{ __("Go to objective") }}'));
@endif

               container.on('click', function(){
                  showDialog(objective.name, infocontainer);
               });
            });
         },
      });
@endif

   });

   function toggleProcessview()
   {
      if($('#processcontainer').hasClass('expanded'))
      {
         $('#processcontainer').removeClass('expanded');
         $('.expandbutton').text('open_in_full');
      }
      else
      {
         $('#processcontainer').addClass('expanded');
         $('.expandbutton').text('close_fullscreen');
      }
   }
</script>
<div class="userdashboard">
   <div class="row">
      <div class="col col-xl-3 col-12 backlog">
         <div class="container useborder">
            <h2>{{ __("Backlog for")." ".auth()->user()->name }}</h2>
            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">pending_actions</span>{{ __("Activities") }}</div>
               <div class="status-container">
@foreach(\App\Models\Activity::where('responsible_user_id', auth()->user()->id)->whereNull('completed_at')->where('due','<',date("Y-m-d", strtotime('+1 MONTHS')))->orderBy('due')->get()->each->setAppends([]) as $issue)
@php
$statuslevel = 'warning';
if($issue->due < date("Y-m-d"))
   $statuslevel = 'danger';
@endphp
                     <div class="status-item">
                        <span class="badge bg-{{ $statuslevel }}"></span>
                        <div><a href="/user/activities?jtId[activitiestable]={{ $issue->id }}">{{ $issue->name }} (@php echo(date("Y-m-d", strtotime($issue->due))); @endphp)</a></div>
                     </div>
@endforeach
               </div>
            </div>
            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">event_list</span>{{ __("Control actions") }}</div>
               <div class="status-container">
@foreach(\App\Models\ControlAction::where('responsible_id', auth()->user()->id)->whereNull('finished_at')->where('due','<',date("Y-m-d", strtotime('+1 MONTHS')))->orderBy('due')->get()->each->setAppends([]) as $issue)
@php
$statuslevel = 'warning';
if($issue->due < date("Y-m-d"))
   $statuslevel = 'danger';
@endphp
                     <div class="status-item">
                        <span class="badge bg-{{ $statuslevel }}"></span>
                        <div><a href="/user/controlactions?jtId[controlactionstable]={{ $issue->id }}">{{ $issue->name }} (@php echo(date("Y-m-d", strtotime($issue->due))); @endphp)</a></div>
                     </div>
@endforeach
               </div>
            </div>
            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">trending_up</span>{{ __("My contributions") }}</div>
               <div class="status-container">
                  @foreach(array_merge(\App\Models\LibraryDocument::getItemsStatus(null, auth()->user(), true)) as $issue)
                     <div class="status-item">
                        <span class="badge bg-{{ $issue['level'] }}"></span>
                        <span class="count">{{ $issue['count'] }}</span>
                        @if($issue['url'])
                           <div><a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a></div>
                        @else
                           <div>{{ $issue['text'] }}</div>
                        @endif
                     </div>
                  @endforeach
               </div>
            </div>
            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">checklist</span>{{ __("Inventory") }}</div>
               <div class="status-container">
      @foreach(array_merge(\App\Models\RequirementSource::getItemsStatus(null, auth()->user(), true), \App\Models\Process::getItemsStatus(null, auth()->user(), true), \App\Models\InformationType::getItemsStatus(null, auth()->user(), true), \App\Models\Asset::getItemsStatus(null, auth()->user(), true), \App\Models\Customer::getItemsStatus(null, auth()->user(), true), \App\Models\Supplier::getItemsStatus(null, auth()->user(), true), \App\Models\Agreement::getItemsStatus(null, auth()->user(), true),\App\Models\Control::getItemsStatus(null, auth()->user(), true), \App\Models\ProcessSustainabilityAspect::getItemsStatus(null, auth()->user(), true), \App\Models\Chemical::getItemsStatus(null, auth()->user(), true)) as $issue)
                  <div class="status-item">
                     <span class="badge bg-{{ $issue['level'] }}"></span>
                     <span class="count">{{ $issue['count'] }}</span>
      @if($issue['url'])
                        <div><a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a></div>
      @else
                        <div>{{ $issue['text'] }}</div>
      @endif
                  </div>
      @endforeach
               </div>
            </div>

            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">frame_inspect</span>{{ __("Assess and mitigate") }}</div>
               <div class="status-container">
      @foreach(array_merge(\App\Models\RiskProject::getItemsStatus(null, auth()->user(), true), \App\Models\Risk::getItemsStatus(null, auth()->user(), true), \App\Models\ComplianceEvaluation::getItemsStatus(null, auth()->user(), true), \App\Models\Finding::getItemsStatus(null, auth()->user(), true), \App\Models\Incident::getItemsStatus(null, auth()->user(), true)) as $issue)
                  <div class="status-item">
                     <span class="badge bg-{{ $issue['level'] }}"></span>
                     <span class="count">{{ $issue['count'] }}</span>
      @if($issue['url'])
                        <div><a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a></div>
      @else
                        <div>{{ $issue['text'] }}</div>
      @endif
                  </div>
      @endforeach
               </div>
            </div>

            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">sports_score</span>{{ __("Measure and improve") }}</div>
               <div class="status-container">
      @foreach(array_merge(\App\Models\ProcessPerformanceMetric::getItemsStatus(null, auth()->user(), true), \App\Models\Objective::getItemsStatus(null, auth()->user(), true)) as $issue)
                  <div class="status-item">
                     <span class="badge bg-{{ $issue['level'] }}"></span>
                     <span class="count">{{ $issue['count'] }}</span>
      @if($issue['url'])
                        <div><a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a></div>
      @else
                        <div>{{ $issue['text'] }}</div>
      @endif
                  </div>
      @endforeach
               </div>
            </div>

            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">mood</span>{{ __("Employee management") }}</div>
               <div class="status-container">
      @foreach(array_merge(\App\Models\Employee::getItemsStatus(null, auth()->user(), true), \App\Models\EmployeeRole::getItemsStatus(null, auth()->user(), true), \App\Models\Competence::getItemsStatus(null, auth()->user(), true)) as $issue)
                  <div class="status-item">
                     <span class="badge bg-{{ $issue['level'] }}"></span>
                     <span class="count">{{ $issue['count'] }}</span>
      @if($issue['url'])
                        <div><a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a></div>
      @else
                        <div>{{ $issue['text'] }}</div>
      @endif
                  </div>
      @endforeach
               </div>
            </div>

            <div class="d-block">
               <div class="container-header"><span class="material-symbols-rounded">settings_alert</span>{{ __("System settings") }}</div>
               <div class="status-container">
      @foreach(array_merge(\App\Models\User::getItemsStatus(null, auth()->user(), true), \App\Models\Site::getItemsStatus(null, auth()->user(), true), \App\Models\Department::getItemsStatus(null, auth()->user(), true), \App\Models\Role::getItemsStatus(null, auth()->user(), true), \App\Models\AccessGroup::getItemsStatus(null, auth()->user(), true)) as $issue)
                  <div class="status-item">
                     <span class="badge bg-{{ $issue['level'] }}"></span>
                     <span class="count">{{ $issue['count'] }}</span>
      @if($issue['url'])
                        <div><a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a></div>
      @else
                        <div>{{ $issue['text'] }}</div>
      @endif
                  </div>
      @endforeach
               </div>
            </div>
         </div>
      </div>
      <div class="col col-xl-9 col-12 rightcontainer">
         <div class="container">
            <div class="row">
               @if(auth()->user()->can('showobjectivestatus.read'))
               <div class="col col-12 col-xl-6">
                  <div id="companyobjectives" class="useborder">
                     <h2>{{ __("Company objectives") }}</h2>
                     <div class="legend">
                        <span class="legend-item"><span class="badge bg-status-ontarget"></span>{{ __("On target") }}</span>
                        <span class="legend-item"><span class="badge bg-status-acceptable"></span>{{ __("Acceptable") }}</span>
                        <span class="legend-item"><span class="badge bg-status-unacceptable"></span>{{ __("Unacceptable") }}</span>
                     </div>
                     <div id="objectivescontainer" class="container row"></div>
                  </div>
               </div>
               @endif
@if(\App\Models\Risk::where('riskowner_id', auth()->user()->id)->whereNull('replacedby_id')->whereNotNull('assessed_at')->count())
               <div class="col col-12 col-xl-6">
                  <div id="riskoverview" class="useborder">
                     <div id="toprisklist">
                        <h2>10 {{ __("top risks") }}</h2>
                        <div class="toprisks">
   @foreach(\App\Models\Risk
      ::where('riskowner_id', auth()->user()->id)
      ->leftJoin('consequence_levels', 'consequence_levels.id', '=', 'risks.consequence_id')
      ->leftJoin('probability_levels', 'probability_levels.id', '=', 'risks.probability_id')
      ->leftJoin('risk_level_mappings', function($join){
         $join->on('risk_level_mappings.probability_level_id', '=', 'probability_levels.id')
              ->on('risk_level_mappings.consequence_level_id', '=', 'consequence_levels.id');
      })
      ->leftJoin('risk_levels', 'risk_level_mappings.risk_level_id', '=', 'risk_levels.id')
      ->whereNull('replacedby_id')
      ->whereNotNull('assessed_at')
      ->orderBy('risk_levels.ordinal', 'desc')
      ->orderBy('consequence_levels.ordinal', 'desc')
      ->select('risks.*')
      ->limit(10)
      ->get()->each->setAppends(['name_pretty']) as $risk)
                              <div class="riskitem">
                                 <div class="risklevel"><span class="badge" style="background-color: #{{ $risk->int_risklevel()->color }}"></span></div>
                                 <div class="riskname"><a href="/assessment/riskregister?jtId[riskregister]={{ $risk->id }}">{{ $risk->name_pretty }}</a></div>
                              </div>
   @endforeach
                        </div>
                     </div>
                     <div id="riskoverviewoutercontainer">
                        <h2>{{ __("Risk overview") }}</h2>
                        <div class="container status-container" style="margin-left: 0;">
                           <div class="status-item"></div>
                           <x-risk-matrix riskowner_id="{{ auth()->user()->id }}" hideheaders />
                        </div>
                     </div>
                  </div>
               </div>
@endif
            </div>
         @if(\App\Models\Process::count())
            <div class="row">
               <div class="col col-12">
                  <div id="processcontainer" class="useborder">
                     <span class="material-symbols-rounded expandbutton" onClick="toggleProcessview();">open_in_full</span>
                     <h2 class="mt-4">{{ __("Process") }}</h2>
                     <div class="mb-4">
                        <select id="processSelector" class="form-select form-select-sm process-selector" onChange="loadProcess($(this).val());">
                           @if(null == $process)
                              <option value="0" selected="true">{{ __("Select process") }}...</option>
                           @endif

                           @foreach(DB::table('processes')->leftJoin('departments', 'processes.department_id', '=', 'departments.id')->orderBy('departments.name')->select(['departments.id', 'departments.name'])->distinct()->get() as $department)
                              <optgroup label="{{ $department->name }}">
                                 @foreach(\App\Models\Process::where('department_id', $department->id)->orderBy('name')->get()->each->setAppends([]) as $obj)
                                    <option value="{{ $obj->id }}" @php if((null != $process) && ($process->id == $obj->id)) echo('selected="true"');@endphp>{{ $obj->name }}</option>
                                 @endforeach
                              </optgroup>
                           @endforeach
                        </select>

                        @if(auth()->user()->can('update', App\Models\Process::class))
                           <a id="editProcessChart" onClick="id=$('#processSelector').val();if(0 < id){ document.location='/inventory/processedit/'+id; }" class="d-none mt-2 btn btn-outline-secondary btn-sm toolbar-button" title="{{ __('Edit process chart') }}"><span class="material-symbols-rounded">draw</span>{{ __("Edit process chart") }}</a>
                        @endif
                     </div>
                     <div id="showdetails" class="form-group">
                        <div class="form-check form-switch">
                           <input class="form-check-input" type="checkbox" role="switch" id="showdetailsbutton" onChange="toggleDetails();loadProcess($('#processSelector').val());">
                           <label class="form-check-label" for="showdetailsbutton">{{ __("Show details") }}</label>
                        </div>
                     </div>

                     <div class="processdescription"></div>
                     <div id="chartcanvas" style="overflow-x: scroll;">
                        <div id="processchart">
                        </div>
                     </div>
                     <div id="doclinkcontainer">
                        <div class="row">
                           <div id="documentlist" class="col col-12 col-md-6"></div>
                           <div id="linklist" class="col col-12 col-md-6"></div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         @endif
      </div>
   </div>
</div>

@endsection
