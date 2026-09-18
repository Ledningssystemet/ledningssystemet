@php if(Auth::user()->cannot('index', \App\Models\ProcessSustainabilityAspect::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Sustainability aspects") }}',
      paging: true,
      searchfield: true,
      tableId: 'sustainabilityaspectregister',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Add new aspect') }}',
      },
      filter: {
@php $tags = \App\Models\ProcessSustainabilityAspect::allUsedTags(); @endphp
@if(0 < count($tags))
         tag_id: {
            type: 'select',
            text: '{{ __("Tag") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
@foreach($tags as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
@endforeach               
            ]
         },
@endif
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\ProcessSustainabilityAspect::class))
         listAction: '/api/v1/items/ProcessSustainabilityAspect',
@endif         
@if(Auth::user()->can('create', \App\Models\ProcessSustainabilityAspect::class))
         createAction: '/api/v1/items/ProcessSustainabilityAspect',
@endif         
@if(Auth::user()->can('update', \App\Models\ProcessSustainabilityAspect::class))
         updateAction: '/api/v1/items/ProcessSustainabilityAspect',
@endif         
@if(Auth::user()->can('delete', \App\Models\ProcessSustainabilityAspect::class))
         deleteAction: '/api/v1/items/ProcessSustainabilityAspect',
@endif         
      },
      fields: {
         id: {
            key: true,
            list: false,
            create: false,
            edit: false,
         },
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            header: true,
            maxlength: 255,
            display: function(data) {
               return $('<span />')
                  .text(data.record.process.name + ' - ' + data.record.sustainability_aspect.name + ' - ' + data.record.name);
               }
         },
         tags: showTags('ProcessSustainabilityAspect', $('#tableContainer')),
         process_id: {
            title: '{{ __('Process') }}',
            create: true,
            edit: true,
            list: true,

            required: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
@foreach(App\Models\Process::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp, Tooltip: @php echo(json_encode($obj->description)); @endphp },
@endforeach
            ],
         },
         sustainability_aspect_id: {
            title: '{{ __('Sustainability aspect') }}',
            create: true,
            edit: false,
            list: true,

            required: true,
            tooltip: '{{ __("Note: this cannot be changed after creation") }}',
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
@foreach(App\Models\SustainabilityAspect::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp, Tooltip: @php echo(json_encode($obj->description)); @endphp },
@endforeach
            ],
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         metric_sum: {
            title: '{{ __('Aspect score') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data) {
               if(null === data.record.metric_sum)
                  return '{{ __("Not assessed") }}';
               else
                  return data.record.metric_sum;
            }
         },
         significant: {
            title: '{{ __('Significant aspect') }}',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-12 col-md-8',
            display: function(data) {
               if(null === data.record.significant)
                  return '{{ __("Not assessed") }}';
               else
                  return (data.record.significant ? '{{ __("Yes") }}' : '{{ __("No") }}');
            }
         },
         sustainability_metrics: {
            title: '',
            create: false,
            edit: true,
            list: true,
            display: function(data) {
               var retobj = $('<div />');
               
               Object.values(data.record.sustainability_metrics).forEach((obj) => {
                  
                  retobj.append($('<div />')
                     .addClass('d-inline-block col-12 col-md-3 jtable-field jtable-field-text')
                     .append($('<span />')
                        .addClass('sustainability_metric_label')
                        .text(obj.name)
                     )
                     .append($('<span />')
                        .addClass('hasdescription')
                        .addClass('sustainability_metric_value')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', obj.level && obj.level.description ? obj.level.description : null)
                        .text((obj.level && obj.level.name) ? obj.level.name + ' ('+obj.level.multiplier+')' : '{{ __("Not assessed") }}')
                     )
                  );
               });

               return retobj;
            },
            input: function(data) {
               var retobj = $('<div />');
               
@foreach(App\Models\SustainabilityMetric::orderBy('name')->get() as $sm)
               var selectedLevel = null;
               var isIncluded = null;
               Object.values(data.record.sustainability_metrics).forEach((obj) => {
                  if(obj.id == {{ $sm->id }})
                  {
                     isIncluded = true;
                     if(obj.level && obj.level.sustainability_metric_level_id)
                        selectedLevel = obj.level.sustainability_metric_level_id;
                  }
               });
               
               if(isIncluded)
               {
                  retobj.append($('<div />')
                     .addClass('jtable-input-field-container')
                     .append($('<div />')
                        .addClass('jtable-input-label')
                        .text(@php echo(json_encode($sm->name)); @endphp))
                     .append($('<div />')
                        .addClass('jtable-input jtable-dropdown-input')
                        .append($('<select />')
                           .addClass('form-control form-select')
                           .attr('name', 'sustainability_metrics[]')
                           .attr('data-metric-id', '{{ $sm->id }}')
@foreach($sm->int_sustainability_metric_levels()->orderBy('multiplier')->get() as $level)
                           .append($('<option />')
                              .attr('value', {{ $level->id }})
                              .attr('selected', selectedLevel && (selectedLevel == {{ $level->id }}))
                              .text(@php echo(json_encode($level->name)); @endphp + ' ({{$level->multiplier}})')
                              
                           )
@endforeach                           
                        )
                     )
                  );
               }
               
@endforeach
               return retobj;
            },
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         impact_description: {
            title: '{{ __('Impact description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         governance_description: {
            title: '{{ __('Governance description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
         },
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         monitoring_description: {
            title: '{{ __('Monitoring description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
         },
         hr4: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         process_performance_metrics: {
            title: '{{ __('Process performance metrics') }}',
            create: false,
            edit: true,
            list: true,

            multiple: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
@foreach(App\Models\ProcessPerformanceMetric::where('quantitative', true)->orderBy('name')->get() as $obj)
<?php
$lastreport = $obj->int_last_report();
if(null != $lastreport)
   $lastreport = $lastreport->reporting_date_at.": ".$lastreport->value." ".$obj->unit;
?>
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name.((null != $lastreport) ? ' ('.$lastreport.')' : ""))); @endphp, Tooltip: @php echo(json_encode($obj->description)); @endphp  },
@endforeach            
            ],
         },
         objectives: {
            title: '{{ __('Objectives') }}',
            create: false,
            edit: true,
            list: true,

            multiple: true,
            listClass: 'd-inline-block col-12 col-md-4',
            options: [
@foreach(App\Models\Objective::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp, Tooltip: @php echo(json_encode($obj->description)); @endphp  },
@endforeach            
            ],
         },
         hr5: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         showHistory: showHistoryField('ProcessSustainabilityAspect', $('#tableContainer')),          
         showMessages: showMessagesField('ProcessSustainabilityAspect', $('#tableContainer')),    
      },
      formCreated: function(event, data) {
         $(data.form).find('#Edit-sustainability_metrics select').each(function(){
            $(this).attr('name', 'sustainability_metrics['+$(this).attr('data-metric-id')+']');
         });
      }
  });
   $('#tableContainer').jtable('load');
});
</script>
<div id="tableContainer"></div>
   
@endsection
