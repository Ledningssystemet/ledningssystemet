@php if(Auth::user()->cannot('index', \App\Models\ComplianceEvaluation::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')
<style>
   .progress {
      margin-top: 20px;
      height: 5px;
   }
   
   .progress-description {
      margin-top: 20px;
      margin-bottom: 20px;
   }
   
   .progress-description > span {
      margin: 5px 20px 5px 0;
   }
   
   .progress-description .badge {
      margin-right: 10px;
      padding: 5px 10px;
   }
   
 </style>
<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Compliance evaluations") }}',
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'evaluationslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create new evaluation') }}',
      },
      filter: {
         hidearchived: {
            type: 'checkbox',
            value: '1',
            checked: true,
            text: '{{ __('Hide archived evaluations') }}',
         },
      },
      actions: {
@if(Auth::user()->can('index', 'App\\Models\\ComplianceEvaluation'))
         listAction: '/api/v1/items/ComplianceEvaluation',
@endif
@if(Auth::user()->can('create', 'App\\Models\\ComplianceEvaluation'))
         createAction: '/api/v1/items/ComplianceEvaluation',
@endif
@if(Auth::user()->can('create', 'App\\Models\\ComplianceEvaluation'))
         updateAction: '/api/v1/items/ComplianceEvaluation',
@endif
@if(Auth::user()->can('create', 'App\\Models\\ComplianceEvaluation'))
         deleteAction: '/api/v1/items/ComplianceEvaluation',
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
            maxlength: 100,
         },
         startdate: {
            title: '{{ __('Startdate') }}',
            type: 'date',
            create: true,
            edit: true,
            list: true,
            required: true,
            listClass: 'd-inline-block col col-12 col-md-3',
            defaultValue: '{{ date("Y-m-d") }}',
         },
         requirement_sourcenames: {
            title: '{{ __('Scope') }}',
            type: 'textarea',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col col-12 col-md-9',
            display: function(data){
               var retval = $('<div />');
               if(data.record.requirement_sources)
               {
                  data.record.requirement_sources.forEach((obj) => {
                     retval.append($('<span />')
                        .text(obj.reference+' '+obj.name)
                        .css({ display: 'block' })
                     );
                  });
               }
               return retval;
            },
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
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         participants: {
            title: '{{ __('Participants') }}',
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
         statistics: {
            title: '{{ __("Progress") }}',
            list: true,
            create: false,
            edit: false,
            display: function(data){
               var progressdiv = $('<div />')
                  .addClass('progress');

               var retval = $('<div />')
                  .addClass('progress-container')
                  .append(progressdiv);
                  
                     
               
               // Passed
               progressdiv.append($('<div />')
                  .addClass('progress-bar bg-success')
                  .attr('role', 'progressbar')
                  .attr('aria-label', 'Passed')
                  .attr('aria-valuenow', data.record.statistics.pass)
                  .attr('aria-valuemin', 0)
                  .attr('aria-valuemax', data.record.statistics.requirements)
                  .css({'width': Math.round(100*(parseFloat(data.record.statistics.pass)/parseFloat(data.record.statistics.requirements)))+'%'})
               );
        
               // Failed
               progressdiv.append($('<div />')
                  .addClass('progress-bar bg-danger')
                  .attr('role', 'progressbar')
                  .attr('aria-label', 'Failed')
                  .attr('aria-valuenow', data.record.statistics.fail)
                  .attr('aria-valuemin', 0)
                  .attr('aria-valuemax', data.record.statistics.requirements)
                  .css({'width': Math.round(100*(parseFloat(data.record.statistics.fail)/parseFloat(data.record.statistics.requirements)))+'%'})
               );
               
               // Not applicable
               progressdiv.append($('<div />')
                  .addClass('progress-bar bg-info')
                  .attr('role', 'progressbar')
                  .attr('aria-label', 'Not applicable')
                  .attr('aria-valuenow', data.record.statistics.na)
                  .attr('aria-valuemin', 0)
                  .attr('aria-valuemax', data.record.statistics.requirements)
                  .css({'width': Math.round(100*(parseFloat(data.record.statistics.na)/parseFloat(data.record.statistics.requirements)))+'%'})
               );
        
               // Not evaluated
               progressdiv.append($('<div />')
                  .addClass('progress-bar bg-dark progress-bar-striped')
                  .attr('role', 'progressbar')
                  .attr('aria-label', 'Not evaluated')
                  .attr('aria-valuenow', data.record.statistics.requirements-data.record.statistics.pass-data.record.statistics.fail-data.record.statistics.na)
                  .attr('aria-valuemin', 0)
                  .attr('aria-valuemax', data.record.statistics.requirements)
                  .css({'width': Math.round(100*(parseFloat(data.record.statistics.requirements-data.record.statistics.pass-data.record.statistics.fail-data.record.statistics.na)/parseFloat(data.record.statistics.requirements)))+'%'})
               );
               
               retval.append($('<div />')
                  .addClass('progress-description')
                  
                  .append($('<span />')
                     .addClass('d-inline-block col col-12 col-md-2')
                     .append($('<span />')
                        .addClass('badge rounded-pill bg-success')
                        .text(data.record.statistics.pass))
                     .append($('<span />')
                        .addClass('progress-description-label')
                        .text('{{ __("Passed"); }}')
                     )
                  )
                  
                  .append($('<span />')
                     .addClass('d-inline-block col col-12 col-md-2')
                     .append($('<span />')
                        .addClass('badge rounded-pill bg-danger')
                        .text(data.record.statistics.fail))
                     .append($('<span />')
                        .addClass('progress-description-label')
                        .text('{{ __("Failed"); }}')
                     )
                  )
                  
                  .append($('<span />')
                     .addClass('d-inline-block col col-12 col-md-2')
                     .append($('<span />')
                        .addClass('badge rounded-pill bg-info')
                        .text(data.record.statistics.na))
                     .append($('<span />')
                        .addClass('progress-description-label')
                        .text('{{ __("Not applicable"); }}')
                     )
                  )
                  
                  .append($('<span />')
                     .addClass('d-inline-block col col-12 col-md-2')
                     .append($('<span />')
                        .addClass('badge rounded-pill bg-dark')
                        .text(data.record.statistics.requirements-data.record.statistics.pass-data.record.statistics.fail-data.record.statistics.na))
                     .append($('<span />')
                        .addClass('progress-description-label')
                        .text('{{ __("Not evaluated"); }}')
                     )
                  )
               );
               
               
               
               return retval;
            }
         },
         hr3: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         actions: {
            title:'',
            list: true,
            edit: false,
            create: false,
            footer: true,
            display: function(data){
               var retval = $('<div />');
               
@if(Auth::user()->can('create', 'App\\Models\\ComplianceEvaluation'))
               if(data.record.finished && !data.record.archived)
               {
                  retval.append($('<button />')
                     .css({'margin-right': '10px'})
                     .addClass('btn btn-sm btn-outline-primary')
                     .text('{{ __("Archive") }}')
                     .click(function(){
                        confirmDialog('{{ __("Archive compliance evaluation") }}', '{{ __("Are you sure that you want to archive this compiance evaluation?") }}', function(){
                           ajaxPatch('/api/v1/items/ComplianceEvaluation/'+data.record.id, { archived: true }, function(){
                              $('#tableContainer').jtable('reload');
                           });
                        }, { warning: true });
                     })
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('archive'))
                  );
               }
               
               if(!data.record.finished)
               {
                  retval.append($('<button />')
                     .addClass('btn btn-sm btn-outline-primary')
                     .css({'margin-right': '10px'})
                     .text('{{ __("Generate checklist") }}')
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('manufacturing'))
                     .click(function(){
                        var dialogMessage = $('<form />');
                        var selectedOptions = [];
                        data.record.requirement_sources.forEach((obj) => {
                           selectedOptions[obj.id] = obj.id;
                        });                           
                        
                        dialogMessage.append($('<span />')
                           .text('{{ __("Note: If checklist is generated, existing evaluations and findings may be affected") }}')
                           .css({'display': 'block', 'font-style': 'italic', 'margin-bottom': '10px'})
                        );
                        
@foreach(\App\Models\RequirementSource::orderBy('reference')->whereNull('not_applicable_at')->select(['id', 'reference', 'name'])->get()->each->setAppends([]) as $rs)
                        dialogMessage.append($('<div />')
                           .addClass('form-check')
                           .append($('<input type="checkbox" name="{{ $rs->id }}" id="cl-{{ $rs->id }}" />')
                              .addClass('form-check-input')
                              .prop('checked', selectedOptions.includes({{ $rs->id }}))
                           )
                           .append($('<label for="cl-{{ $rs->id }}" />')
                              .addClass('form-check-label')
                              .text(@php echo(json_encode($rs->reference.' '.$rs->name)); @endphp)
                           )
                        );
@endforeach                           
                        
                        confirmDialog('{{ __("Generate checklist") }}', dialogMessage, function(formdata){
                           var reqsources = [];
                           formdata.forEach((formitem) => {
                              if(formitem.value)
                                 reqsources.push(parseInt(formitem.name));
                           });
                           ajaxPost('/api/v1/items/ComplianceEvaluation/'+data.record.id+'/generate', { reqsources: reqsources }, function(){ $('#tableContainer').jtable('reload'); });
                        });
                     })
                  );

                  retval.append($('<a href="evaluate/'+data.record.id+'" />')
                     .css({'margin-right': '10px'})
                     .addClass('btn btn-sm btn-outline-primary')
                     .text('{{ __("Open evaluation tool") }}')
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('checklist'))
                  );
                     
                  if(0 == data.record.statistics.open)
                  {
                     retval.append($('<button />')
                        .css({'margin-right': '10px'})
                        .addClass('btn btn-sm btn-outline-primary')
                        .text('{{ __("Finish") }}')
                        .click(function(){
                           confirmDialog('{{ __("Finish compliance evaluation") }}', '{{ __("Are you sure that you want to finish this compiance evaluation?") }}', function(){
                              ajaxPatch('/api/v1/items/ComplianceEvaluation/'+data.record.id, { finished: 1 }, function(){
                                 $('#tableContainer').jtable('reload');
                              });
                           }, { warning: true });
                        })
                        .prepend($('<span />')
                           .addClass('material-symbols-rounded')
                           .text('check_box'))
                     );
                  }
               }
               else if(!data.record.archived)
               {
                  retval.append($('<button />')
                     .css({'margin-right': '10px'})
                     .addClass('btn btn-sm btn-outline-primary')
                     .text('{{ __("Re-open compliance evaluation") }}')
                     .click(function(){
                        confirmDialog('{{ __("Re-open compliance evaluation") }}', '{{ __("Are you sure that you want to re-open this compiance evaluation?") }}', function(){
                           ajaxPatch('/api/v1/items/ComplianceEvaluation/'+data.record.id, { finished: 0 }, function(){
                              $('#tableContainer').jtable('reload');
                           });
                        }, { warning: true });
                     })
                     .prepend($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('lock_open_right'))
                  );
               }
@endif
               retval.append($('<a />')
                  .addClass('btn btn-sm btn-outline-primary')
                  .attr('href', '/api/v1/ReportCentral/ComplianceEvaluation/'+data.record.id)
                  .text('{{ __("Download report") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('inventory'))
               );
               
               return retval;
            }
         },
      },
      recordsLoaded: function(event, data){
         if(data.serverResponse.data)
         {
            data.serverResponse.data.forEach((obj) => {
               if(obj.finished)
               {
                  $(event.target).find('[data-record-key="'+obj.id+'"] .jtable-edit-command').remove();
               }
            });
         }
         else
         {
            if(data.serverResponse.finished)
            {
               $(event.target).find('[data-record-key="'+data.serverResponse.id+'"] .jtable-edit-command').remove();
            }
         }
      },
   });
   $('#tableContainer').jtable('load');
});
</script>

<div id="tableContainer"></div>
   
@endsection
