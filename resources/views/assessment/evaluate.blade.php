@php if(Auth::user()->cannot('update', $evaluation)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<style type="text/css">
   .summary-container {
      margin: 20px 0 60px 0;
      padding-bottom: 30px;
      border-bottom: 1px solid var(--bs-gray-200);
   }
   
   .summary-container button {
      margin-top: 30px;
   }
   
   .note-container, .jtable-reqsource-comments-container  {
      margin-bottom: 20px;
      margin-top: 0.6rem;
   }
   
   .field-applicability:before {
      display: block;
      content: '{{ __("Requirement applicable") }}';
      font-weight: bold;
   }
   
   .field-description:before {
      display: block;
      content: '{{ __("Description") }}';
      font-weight: bold;
      margin-top: 0.6rem;
   }

   .field-governance:before {
      display: block;
      content: '{{ __("Governance") }}';
      font-weight: bold;
      margin-top: 0.6rem;
   }

   .field-governancecontrol:before {
      display: block;
      content: '{{ __("Controls") }}';
      font-weight: bold;
      margin-top: 0.6rem;
   }

   .accordion-item.requirement-source {
      margin: 0 0 10px 0;
   }

   .accordion-item.requirement-source .accordion-body {
      border: 2px;
   }

   .accordion-item.requirement-source .accordion-button {
      font-size: 1rem;
   }

   .jtable-bootstrap .accordion-header {
      font-size: 1em;
   }

</style>
<script>
$(function(){
@foreach(DB::table('compliance_evaluation_requirement_source')->leftJoin('requirement_sources', 'requirement_sources.id', '=', 'compliance_evaluation_requirement_source.requirement_source_id')->where('compliance_evaluation_id', $evaluation->id)->orderBy('requirement_sources.reference')->select(['compliance_evaluation_requirement_source.id', 'name', 'reference', 'compliance_evaluation_requirement_source.requirement_source_id','compliance_evaluation_requirement_source.note'])->get() as $reqsource)
   $('#tableContainer-{{ $reqsource->id }}').jtable({
      title: '&nbsp;',
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'evaluationslist-{{ $reqsource->id }}',
      bootstrap: true,
      accordion: true,
      filter: {
         hideevaluated: {
            type: 'checkbox',
            text: '{{ __("Hide evaluated items") }}',
            value: 1,
            checked: true,
         }
      },
      actions: {
         listAction: '/api/v1/items/ComplianceEvaluationRequirement?compliance_evaluation_id={{$evaluation->id}}&requirement_source_id={{ $reqsource->requirement_source_id }}',
      },
      fields: {
         id: {
            key: true,
            list: false,
         },
         requirement: {
            title: '{{ __('Requirement') }}',
            type: 'text',
            list: true,
            header: true,
            listClass: 'd-inline-block col col-11',
            display: function(data){
               return $('<span />')
                  .attr('name','req-'+data.record.id)
                  .text(data.record.reference+' '+data.record.name);
            }
         },    
         input: {
            title: '',
            list: true,
            display: function(data) {
               var retval = $('<div />');
                  
               retval.append($('<div />')
                  .addClass('field-applicability')
                  .text(data.record.requirement_applicable ? '{{ __("Yes") }}' : '{{ __("No") }}'));
                  
               if(data.record.description)
                  retval.append($('<div />')
                     .addClass('field-description')
                     .text(data.record.description)
                  );
                     
               if(data.record.governance)
                  retval.append($('<div />')
                     .addClass('field-governance')
                     .text(data.record.governance)
                  );
                     
               if(data.record.controls && (data.record.controls.length > 0))
               {
                  var controlcontainer = $('<div />')
                     .addClass('field-governancecontrol')
                     .appendTo(retval)

                  data.record.controls.forEach((cdata) => {
                     controlcontainer.append($('<div />')
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', (cdata.description ? cdata.description : '')+(cdata.statusdescription ? '\r\n\r\nStatus: '+cdata.statusdescription : ''))
                        .text(cdata.name));
                  });
                  
               }
               
               retval.append($('<div />')
                  .addClass('note-container')
                  .append($('<textarea />')
                     .addClass('note form-control')
                     .prop('placeholder', '{{ __("Enter evaluation comment here...") }}')
                     .val(data.record.note)
                  )
               );
@if(!config('ledningssystemet.disable_finding'))
               
               var findingButton = $('<button />')
                  .addClass('btn btn-sm btn-outline-primary btn-findings')
                  .text('{{ __("Findings") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded'+((data.record.nccount || data.record.obscount) ? ' text-danger' : ''))
                     .text('report'))
                  .click(function () {
                     if(retval.closest('.accordion-body').find('#findingsTable').length)
                     {
                        $('#tableContainer-{{ $reqsource->id }}').jtable('closeChildTable', retval.closest('.accordion-body').find('.accordion-footer'));
                        return;
                     }
                     
                     $('#tableContainer-{{ $reqsource->id }}').jtable('openChildTable',
                     $(this).closest('.accordion-body').find('.accordion-footer'), 
                     {
                        title: '{{ __("Findings") }}',
                        tableId: 'findingsTable',
                        messages: {
                           addNewRecord: '{{ __('New finding') }}',
                        },
                        actions: {
                           listAction: '/api/v1/items/ComplianceEvaluationRequirementFinding?compliance_evaluation_requirement_id='+data.record.id,
                           createAction: '/api/v1/items/ComplianceEvaluationRequirementFinding?compliance_evaluation_requirement_id='+data.record.id,
                           updateAction: '/api/v1/items/ComplianceEvaluationRequirementFinding',
                           deleteAction: '/api/v1/items/ComplianceEvaluationRequirementFinding',
                        },
                        fields: {
                           id: {
                              key: true,
                              list: false,
                              create: false,
                              edit: false,
                           },
                           isnc: {
                              title: '{{ __('Category') }}',
                              create: true,
                              edit: true,
                              list: true,
                              width: '20%',
                              defaultValue: 1,
                              options: {
                                 0: '{{ __('Observation') }}',
                                 1: '{{ __('Non-conformity') }}',
                              }
                           },
                           department_id: {
                              title: '{{ __('Department') }}',
                              create: true,
                              edit: true,
                              list: true,
                              width: '20%',
                              options: [
@foreach(\App\Models\Department::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach                                 
                              ]
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              maxlength: 100,
                              width: '20%',
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              width: '45%',
                           },
                        },
                        recordsLoaded: function(event,data)
                        {
                           if(data.serverResponse.total)
                           {
                              $(event.target).closest('.jtable-data-row').find('.btn-findings .material-symbols-rounded').css({'color': 'var(--bs-danger)'});
                              $(event.target).closest('.jtable-data-row').find('.accordion-header .material-symbols-rounded').css({'color': 'var(--bs-warning)'}).text('report');
                           }
                           else
                           {
                              $(event.target).closest('.jtable-data-row').find('.btn-findings .material-symbols-rounded').css({'color': 'var(--bs-info)'});
                              $(event.target).closest('.jtable-data-row').find('.accordion-header .material-symbols-rounded').css({'color': 'inherit'}).text('check');
                           }
                              
                        },
                        recordDeleted: function(event, data) {
                           $('#findingsTable').closest('.jtable-child-table-container').jtable('reload');
                        },
                        recordUpdated: function(event, data) {
                           $('#findingsTable').closest('.jtable-child-table-container').jtable('reload');
                        },
                        recordAdded: function(event, data) {
                           $('#findingsTable').closest('.jtable-child-table-container').jtable('reload');
                        }
                     }, function (data) {
                         data.childTable.jtable('load');
                     }
                  )
               }).appendTo(retval);
               
@endif               

               retval.append($('<button />')
                  .addClass('btn btn-sm btn-outline-primary')
                  .css({'margin-left': '10px'})
                  .text('{{ __("Save comment") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('save'))
                  .click(function(){
                     ajaxPatch('/api/v1/items/ComplianceEvaluationRequirement/'+data.record.id, { note: $(this).closest('div').find('textarea.note').val() }, function(){
                        $('#tableContainer-{{ $reqsource->id }}').jtable('reloadRow', $(this).closest('.jtable-data-row'));
                     });
                  })
               );
               retval.append($('<button />')
                  .addClass('btn btn-sm ' + ((data.record.evaluated && data.record.applicable) ? 'btn-primary text-white' : 'btn-outline-primary'))
                  .css({'margin-left': '10px'})
                  .text('{{ __("Evaluation complete") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('check'))
                  .click(function(){
                     if(data.record.evaluated && data.record.applicable)
                        ajaxPatch('/api/v1/items/ComplianceEvaluationRequirement/'+data.record.id, { evaluated: 0, applicable: 1, note: $(this).closest('div').find('textarea.note').val() }, function(){
                           $('#tableContainer-{{ $reqsource->id }}').jtable('reload');
                        });
                     else
                        ajaxPatch('/api/v1/items/ComplianceEvaluationRequirement/'+data.record.id, { evaluated: 1, applicable: 1, note: $(this).closest('div').find('textarea.note').val() }, function(){
                           $('#tableContainer-{{ $reqsource->id }}').jtable('reload');
                        });
                  })
               );
               
               retval.append($('<button />')
                  .addClass('btn btn-sm ' + ((data.record.evaluated && !data.record.applicable) ? 'btn-primary text-white' : 'btn-outline-primary'))
                  .css({'margin-left': '10px'})
                  .text('{{ __("Not applicable") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('close'))
                  .click(function(){
                     if(data.record.evaluated && !data.record.applicable)
                        ajaxPatch('/api/v1/items/ComplianceEvaluationRequirement/'+data.record.id, { evaluated: 0, applicable: 0, note: $(this).closest('div').find('textarea.note').val() },function(){
                           $('#tableContainer-{{ $reqsource->id }}').jtable('reload');
                        });
                     else
                        ajaxPatch('/api/v1/items/ComplianceEvaluationRequirement/'+data.record.id, { evaluated: 1, applicable: 0, note: $(this).closest('div').find('textarea.note').val() },function(){
                           $('#tableContainer-{{ $reqsource->id }}').jtable('reload');
                        });

                  })
               );
                  
                  
               return retval;
            },
         },
      },
      recordsLoaded: function(event, data){
         if(0 < $(event.target).find('.jtable-reqsource-comments-container').length)
            return;

         $(event.target).find('.jtable').prepend(
            $('<div />')
               .addClass('jtable-reqsource-comments-container')
               .append($('<label for="summary-reqsource-{{ $reqsource->id }}" />')
                  .addClass('form-label')
                  .text('{{ __("Notes") }}'))
               .append($('<textarea id="summary-reqsource-{{ $reqsource->id }}" />')
                  .addClass('form-control note')
                  .attr('rows', 3)
                  .prop('placeholder', '{{ __("Here you may enter any comments regarding the evaluation of this requirement source") }}')
                  .prop('value', @php echo(json_encode($reqsource->note)); @endphp )
               )
               .append($('<button />')
                  .addClass('btn btn-sm btn-outline-primary mt-2')
                  .text('{{ __("Save") }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('save'))
                  .click(function(){
                     ajaxPatch('/api/v1/items/ComplianceEvaluationRequirementSource/{{ $reqsource->id}}', { note: $(this).closest('div').find('textarea.note').val() }, function(){
                        $('#tableContainer-{{ $reqsource->id }}').jtable('reload');
                     });
                  })
               )
               .append($('<button />')
                     .addClass('btn btn-sm btn-outline-primary mt-2 mx-2')
                     .text('{{ __("Approve all not yet assessed requirements") }}')
                     .append($('<span />')
                        .addClass('material-symbols-rounded')
                        .text('check'))
                     .click(function(){
                        var pendingApprovals = [];
                        Object.values(data.serverResponse.data).forEach((record) => {
                           if (!record.evaluated) {
                              pendingApprovals[record.id] = record.id;
                           }
                        });

                        Object.values(pendingApprovals).forEach((recordId) => {
                           ajaxPatch('/api/v1/items/ComplianceEvaluationRequirement/' + recordId, {
                              evaluated: 1,
                              applicable: 1,
                              note: ''
                           }, function () {
                              delete pendingApprovals[recordId];
                              if (Object.keys(pendingApprovals).length === 0) {
                                 $('#tableContainer-{{ $reqsource->id }}').jtable('reload');
                              }
                           });
                        });
                     })
               )
         );
      },
   });
   $('#tableContainer-{{ $reqsource->id }}').jtable('load');
@endforeach

   // Set summary
   $('textarea#summary').val(@php echo(json_encode($evaluation->summary)); @endphp);

   var filterChangeOngoing = false;
   $('input[data-key="hideevaluated"]').on('change', function(event) {
      if(filterChangeOngoing)
         return;

      filterChangeOngoing = true;
      $('input[data-key="hideevaluated"]').prop('checked', $(this).is(':checked')).trigger('change');
      filterChangeOngoing = false;
   });
});

function savesummary()
{
   var tapos = $('textarea#summary').position();
   var taheight = $('textarea#summary').height();
   var tawidth = $('textarea#summary').width();
   
   ajaxPatch('/api/v1/items/ComplianceEvaluation/{{ $evaluation->id }}', { summary: $('textarea#summary').val(), });
}
</script>
<a href="../evaluations" class="btn btn-outline-primary btn-return"><span class="material-symbols-rounded">arrow_back</span>{{ __("Return to compliance requirement list") }}</a><br><br>
<h1>{{ $evaluation->name }}</h1>
<div class="summary-container">
   <label class="form-label" for="summary">{{ __("Executive summary") }}</label>
   <textarea id="summary" class="form-control" placeholder="{{ __("Enter your general impression based on the performed evaluation") }}" rows="3"></textarea>
   <button id="savesummary" class="btn btn-sm btn-outline-primary" onClick="savesummary();"><span class="material-symbols-rounded">save</span>{{ __("Save") }}</button>
</div>

<div class="accordion">

@php $row = 0; @endphp
@foreach(DB::table('compliance_evaluation_requirement_source')->leftJoin('requirement_sources', 'requirement_sources.id', '=', 'compliance_evaluation_requirement_source.requirement_source_id')->where('compliance_evaluation_id', $evaluation->id)->orderBy('requirement_sources.reference')->select(['compliance_evaluation_requirement_source.id', 'name', 'reference'])->get() as $reqsource)
@php $row++; @endphp

   <div class="accordion-item requirement-source">
      <h2 class="accordion-header" id="accordion-heading-{{ $reqsource->id }}">
         <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-collapse-{{ $reqsource->id }}">
            {{ $reqsource->reference.' '.$reqsource->name }}
         </button>
      </h2>
      <div id="accordion-collapse-{{$reqsource->id}}"class="accordion-collapse collapse">
         <div class="accordion-body">
            <div id="tableContainer-{{ $reqsource->id }}"></div>
         </div>
      </div>
   </div>
@endforeach
</div>
@endsection
