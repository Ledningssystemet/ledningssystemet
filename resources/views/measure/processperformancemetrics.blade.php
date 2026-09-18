@php if(Auth::user()->cannot('index', \App\Models\ProcessPerformanceMetric::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')
<style type="text/css">
   span.badge {
      margin: 0 3px 0 3px;
   }

</style>
<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Process metrics") }}',
      paging: true,
      searchfield: true,
      tableId: 'metricslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create metric') }}',
      },
      filter: {
         @php $tags = \App\Models\ProcessPerformanceMetric::allUsedTags(); @endphp
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
         responsible_user_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('process_performance_metrics', 'process_performance_metrics.responsible_user_id', '=', 'users.id')->whereNotNull('process_performance_metrics.id')->select('users.*')->distinct()->orderBy('users.name')->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my metrics') }}',
         },
         @include('components.customproperty', ['classname' => 'App\Models\ProcessPerformanceMetric', 'showFilter' => true])
      },
      actions: {
@if(Auth::user()->can('index', 'App\\Models\\ProcessPerformanceMetric'))
         listAction: '/api/v1/items/ProcessPerformanceMetric',
@endif
@if(Auth::user()->can('create', 'App\\Models\\ProcessPerformanceMetric'))
         createAction: '/api/v1/items/ProcessPerformanceMetric',
@endif
@if(Auth::user()->can('update', 'App\\Models\\ProcessPerformanceMetric'))
         updateAction: '/api/v1/items/ProcessPerformanceMetric',
@endif
@if(Auth::user()->can('delete', 'App\\Models\\ProcessPerformanceMetric'))
         deleteAction: '/api/v1/items/ProcessPerformanceMetric',
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
         },
         graph: {
            title: '',
            create: false,
            edit: false,
            list: true,
            display: function(data){
               return $('<div />')
                  .addClass('chartarea');
            }
         },
         tags: showTags('ProcessPerformanceMetric', $('#tableContainer')),
         metric_type: {
            title: '{{ __('Metric type') }}',
            create: true,
            edit: false,
            list: true,
            required: true,
            listClass: 'd-inline-block col-12 col-md-6',
            tooltip: '{{ __("This cannot be changed after creation") }}',
            options: [
               { Value: 1, DisplayText: '{{ __("Quantitative metric, higher value is preferrable") }}' },
               { Value: 2, DisplayText: '{{ __("Quantitative metric, lower value is preferrable") }}' },
               { Value: 3, DisplayText: '{{ __("Non-quantitative metric") }}' },
            ],
         },
         metric_type_edit: {
            title: '',
            create: false,
            edit: true,
            list: false,
            input: function(data) {
               return '<input id="Edit-metric_type" type="hidden" value="'+data.record.metric_type+'" />';
            }
         },
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,

            required: true,
            listClass: 'd-inline-block col-12 col-md-4',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
@foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
            ],
         },
         processes: {
            title: '{{ __('Processes') }}',
            create: false,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-8',
            multiple: true,
            options: [
@foreach(App\Models\Process::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{$obj ->id}}, DisplayText: @php echo(json_encode($obj->name)); @endphp  },
@endforeach
            ],
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         unit: {
            title: '{{ __('Unit') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            listClass: 'd-inline-block col-12 col-md-3',
            maxlength: 30,
         },
         report_interval: {
            title: '{{ __('Report interval') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-9',
            options: [
@foreach(array_keys(\App\Models\ProcessPerformanceMetric::getIntervals()) as $objkey)
               { Value: {{ $objkey }}, DisplayText: '{{ \App\Models\ProcessPerformanceMetric::getIntervals()[$objkey]['text'] }}' },
@endforeach            
            ],
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         precision: {
            title: '{{ __('Decimals') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            type: 'number',
            defaultValue: 0,
            min: 0,
            max: 9,
            listClass: 'd-inline-block col-12 col-md-3',
         },
         min: {
            title: '{{ __('Minimum value') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            type: 'number',
            defaultValue: 0,
            step: 1,
            listClass: 'd-inline-block col-12 col-md-3',
         },
         max: {
            title: '{{ __('Maximum value') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            type: 'number',
            defaultValue: 0,
            step: 1,
            listClass: 'd-inline-block col-12 col-md-3',
         },
         alarm_threshold: {
            title: '{{ __('Alarm threshold') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            type: 'number',
            step: 1,
            listClass: 'd-inline-block col-12 col-md-3',
         },
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />');
            }
         },
         postprocessing_function: {
            title: '{{ __('Postprocessing function') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            options: [
               { Value: null, DisplayText: '{{ __('None') }}' },
@foreach(array_keys(\App\Models\ProcessPerformanceMetric::getMathFunctions()) as $objkey)
   { Value: '{{ $objkey }}', DisplayText: '{{ \App\Models\ProcessPerformanceMetric::getMathFunctions()[$objkey] }}'},
@endforeach
            ],
         },
         postprocessing_function_parameter1: {
            title: '{{ __('Parameter {x}') }}',
            create: true,
            edit: true,
            list: true,
            required: false,
            type: 'number',
            defaultValue: 0,
            min: 0,
            step: 1,
            listClass: 'd-inline-block col-6 col-md-4',
         },
         @include('components.customproperty', ['classname' => 'App\Models\ProcessPerformanceMetric'])
         hr4: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         reports: {
            title: '',
            type: 'command',
            width: '1%',
            sorting: false,
            edit: false,
            create: false,
            footer: true,
            display: function (sourcedata) {
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Reports') }}')
                  .prepend($('<span>trending_up</span>')
                     .addClass('material-symbols-rounded'));
                  
                  
               if(sourcedata.record.reportcount)
                  retobj.find('.material-symbols-rounded').css({'color': 'var(--bs-danger)'});
               
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#reports-table').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                   $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Reports") }}',
                        tableId: 'reports-table',
                        paging: true,
                        actions: {
                           listAction: '/api/v1/items/ProcessPerformanceMetricReport?process_performance_metric_id='+sourcedata.record.id,
                           createAction: '/api/v1/items/ProcessPerformanceMetricReport',
                           deleteAction: '/api/v1/items/ProcessPerformanceMetricReport',
                        },
                        fields: {
                           process_performance_metric_id: {
                              type: 'hidden',
                              create: true,
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                           },
                           reporting_date_at: {
                              title: '{{ __("Date") }}',
                              type: 'date',
                              list: true,
                              create: true,
                              defaultValue: '{{ date("Y-m-d") }}',
                           },
                           reportvalue: {
                              title: '{{ __("Reported value") }}',
                              list: true,
                              create: true,
                              required: true,
                              type: 'number',
                              min: sourcedata.record.min,
                              max: sourcedata.record.max,
                              step: '1e-'+sourcedata.record.precision,
                              display: function(data){
                                 if(3 == sourcedata.record.metric_type)
                                    return '-';
                                 
                                 return data.record.reportvalue + (sourcedata.record.unit ? ' ' + sourcedata.record.unit : '');
                              }
                           },
                           calculatedvalue: {
                              title: '{{ __("Calculated value") }}',
                              list: true,
                              create: false,
                              display: function(data){
                                 if(3 == sourcedata.record.metric_type)
                                    return '-';

                                 return data.record.calculatedvalue + (sourcedata.record.unit ? ' ' + sourcedata.record.unit : '');
                              }
                           },
                           comment: {
                              title: '{{ __("Comment") }}',
                              type: 'textarea',
                              list: true,
                              create: true,
                           },
                           reported_by: {
                              title: '{{ __("Reported by") }}',
                              list: true,
                              create: false,
                           },
                        },
                        formCreated: function(event, data) {
                           if(3 == sourcedata.record.metric_type)
                              data.form.find('#Edit-reportvalue').closest('.jtable-input-field-container').remove();
                        },
                        recordDeleted: function(event, data) {
                           drawKpi(event.target);
                        },
                        recordAdded: function(event, data) {
                           drawKpi(event.target);
                        },
                        
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },     
         showHistory: showHistoryField('ProcessPerformanceMetric', $('#tableContainer')),          
         showMessages: showMessagesField('ProcessPerformanceMetric', $('#tableContainer')),    
      },
      rowLoaded: function(event, data){
         if(!data.record.responsible_user_id)
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="responsible_user_id"] .jtable-field-label').addClass('text-danger');
         
         if(3 == data.record.metric_type)
         {
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="hr1"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="hr2"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="hr3"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="unit"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="min"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="max"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="precision"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="postprocessing_function"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="postprocessing_function_parameter1"]').remove();
            $('#jtable-body-metricslist > .jtable-data-row[data-record-key="'+data.record.id+'"] [data-jtable-fieldname="alarm_threshold"]').remove();
         }
      },
      formCreated: function(event, data) {
         // Create form
         data.form.find('#Edit-metric_type')
            .on('change', function(){
               if(3 == $(this).val()) {
                  data.form.find('#Edit-unit').closest('.jtable-input-field-container').hide();
                  data.form.find('#Edit-min').closest('.jtable-input-field-container').hide();
                  data.form.find('#Edit-max').closest('.jtable-input-field-container').hide();
                  data.form.find('#Edit-precision').closest('.jtable-input-field-container').hide();
                  data.form.find('#Edit-postprocessing_function').closest('.jtable-input-field-container').hide();
                  data.form.find('#Edit-postprocessing_function_parameter1').closest('.jtable-input-field-container').hide();
                  data.form.find('#Edit-alarm_threshold').closest('.jtable-input-field-container').hide();
               }
               else {
                  data.form.find('#Edit-unit').closest('.jtable-input-field-container').show();
                  data.form.find('#Edit-min').closest('.jtable-input-field-container').show();
                  data.form.find('#Edit-max').closest('.jtable-input-field-container').show();
                  data.form.find('#Edit-precision').closest('.jtable-input-field-container').show();
                  data.form.find('#Edit-postprocessing_function').closest('.jtable-input-field-container').show();
                  data.form.find('#Edit-postprocessing_function_parameter1').closest('.jtable-input-field-container').show();
                  data.form.find('#Edit-alarm_threshold').closest('.jtable-input-field-container').show();
               }
            })
            .trigger('change');
         
         // Edit form
         
         data.form.find('#Edit-precision')
            .on('change', function(){
               data.form.find('#Edit-min').attr('step', '1e-'+$(this).val());
               data.form.find('#Edit-max').attr('step', '1e-'+$(this).val());
            })
            .trigger('change');
      },
      recordsLoaded: function(event, data) {
         $('#tableContainer .accordion-button.collapsed').on('click', function(){
            drawKpi(this);
         });
      }
   });
   $('#tableContainer').jtable('load');      
});

function drawKpi(target)
{
   var dataRow = $(target).closest('.jtable-data-row');
   dataRow.find('.chartarea').empty();
   if(3 == dataRow.data('record').metric_type)
      return;
   
   var chartArea = $('<canvas></canvas>').css({'max-height': '200px'}).hide().appendTo(dataRow.find('.chartarea'));
   ajaxGet('/api/v1/items/ProcessPerformanceMetricReport?process_performance_metric_id='+dataRow.data('record-key'), function(tabledata){
      if(tabledata.total)
      {
           var dataset = [];
           for(var i = tabledata.data.length-1; i >= 0; i--)
              dataset.push({ date: tabledata.data[i].reporting_date_at, value: tabledata.data[i].calculatedvalue });
           
           showKpi(chartArea, dataset, '');
           chartArea.show();
      }
   });
}

</script>
<div id="tableContainer"></div>
   
@endsection
