@php if(Auth::user()->cannot('index', \App\Models\RiskProject::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>

$(function(){
   $('#tableContainer').jtable({
      paging: true,
      sorting: false,
      defaultSorting: 'name ASC',
      searchfield: true,
      tableId: 'riskprojects',
      openChildAsAccordion: true,
      bootstrap: true,
      accordion: true,
      title: '{{ __("Risk projects") }}',
      messages: {
         addNewRecord: '{{ __('Add new risk project') }}',
      },
      filter: {
         responsible_user_id: {
            type: 'select',
            text: '{{ __("Responsible user") }}',
            default: 0,
            options: [
               { value: 0, text: '{{ __('Show all') }}' },
                  @foreach(\App\Models\User::leftJoin('risk_projects', 'risk_projects.responsible_user_id', '=', 'users.id')->whereNotNull('risk_projects.id')->select('users.*')->distinct()->orderBy('users.name')->select(['users.id', 'users.name'])->get()->each->setAppends([]) as $obj)
               { value: {{ $obj->id }}, text: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         showarchived: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show archived') }}',
         },
         showmyonly: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Show only my risk projects') }}',
         },
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\RiskProject::class))         
         listAction: '/api/v1/items/RiskProject',
@endif         
@if(Auth::user()->can('create', \App\Models\RiskProject::class))         
         createAction: '/api/v1/items/RiskProject',
@endif         
@if(Auth::user()->can('update', \App\Models\RiskProject::class))         
         updateAction: '/api/v1/items/RiskProject',
@endif         
      },
      fields: {
         id: {
            key: true,
            list: false,
            create: false,
            edit: false,
         },
         risk_project_type_id: {
            title: '{{ __('Risk project type') }}',
            create: true,
            edit: false,
            list: false,
            options: [
               { Value: null, DisplayText: '{{ __("None") }}' },
@foreach(App\Models\RiskProjectType::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
@endforeach
            ]
            
         },
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            header: true,
            maxlength: 255,
            display: function(data){
               var retval = $('<span />').text(data.record.name);
               if(data.record.maxrisklevel)
               {
                  retval.append($('<span></span>')
                     .addClass('badge rounded-pill')
                     .css({'background-color': '#'+data.record.maxrisklevel.color+'bb',
                           'margin-left': '10px'})
                     .text(data.record.maxrisklevel.name)
                  );
               }

               return retval;
            }
         },
         department_id: {
            title: '{{ __('Department') }}',
            create: true,
            edit: true,
            list: true,

            required: true,
            listClass: 'd-inline-block col-12 col-md-3',
            defaultValue: <?php $userdeps = auth()->user()->int_departments()->select('departments.id')->get()->each->setAppends([]); echo((0 == count($userdeps)) ? '-1' : $userdeps[0]->id); ?>,
            options: [
@foreach(App\Models\Department::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
@endforeach
            ]
         },
         end_date: {
            title: '{{ __('End date') }}',
            type: 'date',
            defaultValue: @php echo(json_encode(date("Y-m-d"), strtotime("+3 WEEKS"))); @endphp,
            create: true,
            edit: true,
            list: true,
            required: true,
            tooltip: '{{ __("This date represents when all risks are to be assessed") }}',
            listClass: 'd-inline-block col-12 col-md-3',
         },
         risk_distribution: {
            title: '',
            create: false,
            edit: false,
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            display: function(data){
               var retval = $('#riskmatrixtemplate .riskmatrix-container').clone();
               retval.find('*').addBack().contents().filter(function() {
                  return this.nodeType === 3 && !/\S/.test(this.nodeValue);
               }).remove();

               for(const [prob, value] of Object.entries(data.record.riskdistribution)){
                  for(const [cons, attrs] of Object.entries(data.record.riskdistribution[prob])){
                     retval.find('.riskmatrix-col-item span[data-probability-id="'+prob+'"][data-consequence-id="'+cons+'"]').text(attrs.count);
                  }
               }

               return retval;
            }
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         responsible_user_id: {
            title: '{{ __('Responsible user') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            defaultValue: {{ auth()->user()->id }},
            options: [
                  @foreach(App\Models\User::orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
               { Value: {{$obj->id}}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
               @endforeach
            ]
         },
         users: {
            title: '{{ __('Participants') }}',
            create: false,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            multiple: true,
            tooltip: '{{ __("Participants will be able to view the project and its related risks") }}',
            options: [
@foreach(App\Models\User::where('enabled', true)->orderBy('name')->select(['id', 'name'])->get()->each->setAppends([]) as $obj)
            { Value: {{ $obj->id }}, DisplayText: <?php echo(json_encode($obj->name)); ?> },
@endforeach
            ]
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         scopedescription: {
            title: '{{ __('Scope description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            tooltip: '{{ __("Desribe what scope of the risk project") }}',
            listClass: 'd-inline-block col-12 col-md-6',
         },
         purposedescription: {
            title: '{{ __('Purpose description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: false,
            tooltip: '{{ __("Desribe the purpose of performing this risk assessment") }}',
            listClass: 'd-inline-block col-12 col-md-6',
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(auth()->user()->can('useai'))
         ai_agent: {
            title: '',
            type: 'command',
            create: false,
            edit: false,
            list: true,
            footer: true,
            display: function(data) {
               var retobj = $('<div />');
               retobj.append($('<button />')
                  .addClass('btn btn-primary btn-sm text-light')
                  .text('{{ __('AI Risk identification') }}')
                  .prepend($('<span>support_agent</span>')
                     .addClass('material-symbols-rounded'))
                  .click(function(){
                     ajaxPost('/api/v1/ai/riskidentification', { context_type: 'App\\Models\\RiskProject', context_id: data.record.id }, function(aidata, textStatus, jqXHR){
                        if(null == aidata.riskcount)
                           showDialog('{{ __("AI Risk identification") }}', '{{ __("No response was received from AI agent") }}');
                        else
                           showDialog('{{ __("AI Risk identification") }}', aidata.riskcount+' {{ __("new risks were identified") }}');
                     });

                     return false;
                  }));
               return retobj;
            }
         },
@endif
         risks: {
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
                  .text('{{ __('Risks') }}')
                  .prepend($('<span>warning</span>')
                     .addClass('material-symbols-rounded'));
                  
               var actions = {};
               
               actions.listAction ='/api/v1/items/Risk?risk_project_id='+sourcedata.record.id;
               if(!sourcedata.record.archived_at)
               {
                  actions.createAction = '/api/v1/items/Risk';
                  actions.updateAction = '/api/v1/items/Risk';
                  actions.deleteAction = '/api/v1/items/Risk';
               }
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#riskstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                   $('#tableContainer').jtable('openChildTable', retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{ __("Risks") }}',
                        actions: actions,
                        tableId: 'riskstable',
                        bootstrap: true,
                        accordion: true,
                        messages: {
                           addNewRecord: '{{ __('Add new risk') }}',
                        },
@include('assessment.risktablefields')      
                        recordsLoaded: function(event, data) {
                           $(event.target).find('.jtable-data-row .accordion-footer')
                              .append($('<div class="jtable-command-column" />')
                                 .append($('<button title="{{ __("Detach") }}" class="jtable-command-button btn btn-sm btn-outline-primary" />')
                                    .text("{{ __("Detach") }}")
                                    .append($('<span title="{{ __("Detach") }}" class="material-symbols-rounded">')
                                       .text('delete')
                                    )
                                    .on('click', function(event){
                                       var riskid = $(event.target).closest('.jtable-data-row').data('record-key');
                                       console.log(event.target);
                                       confirmDialog('{{ __("Detach risk") }}', '{{ __("By detaching this risk, it will be moved to the risk register") }}', function(formdata){
                                          ajaxPost('/api/v1/items/Risk/'+riskid+'/detach', {}, function(){
                                             $('#tableContainer').jtable('reload');
                                          });
                                       }, { warning: true });
                                    })
                                 )
                              );
                        },
                        formCreated: function(event, data){
                           $(data.form).append($('<input type="hidden" id="Edit-risk_project_id" name="risk_project_id" value="'+sourcedata.record.id+'" />'));
                        },
                        recordDeleted: function(event, data){
                           $('#tableContainer').jtable('reloadProperty', $(event.target).closest('.jtable-data-row'), ['name', 'risk_distribution']);
                        },
                        recordUpdated: function(event, data){
                           $('#tableContainer').jtable('reloadProperty', $(event.target).closest('.jtable-data-row'), ['name', 'risk_distribution']);
                        },
                        recordAdded: function(event, data){
                           $('#tableContainer').jtable('reloadProperty', $(event.target).closest('.jtable-data-row'), ['name', 'risk_distribution']);
                        }
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
               return retobj;
            }
         },         
         showHistory: showHistoryField('RiskProject', $('#tableContainer')),          
         showMessages: showMessagesField('RiskProject', $('#tableContainer')),
         exportxlsx: {
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               var retobj= $('<a />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{__("Export as Excel"); }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('download'))
                  .attr('href', '/api/v1/ReportCentral/RiskProject/'+data.record.id);

               return retobj;
            }
         },
         archive: {
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               if(!data.record.access.update)
                  return "";

               var retobj= $('<a />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .css({cursor: 'pointer'})
                  .text(data.record.archived_at ? '{{__("Unarchive project"); }}' : '{{__("Archive project"); }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('archive'))
                  .on('click', function(){
                     if(data.record.archived_at)
                     {
                        confirmDialog('{{ __("Unarchive project") }}', '{{ __("By unarchiving this project, risk management will be continued.") }}', function(formdata){
                           ajaxPost('/api/v1/items/RiskProject/'+data.record.id+'/unarchive', {}, function(){
                              $('#tableContainer').jtable('reload');
                           });
                        }, { warning: true });
                     }
                     else
                     {
                        confirmDialog('{{ __("Archive project") }}', '{{ __("By archiving this project, you claim that no more risk management is to be done regarding this project. Risks will not be re-opened for assessment.") }}', function(formdata){
                           ajaxPost('/api/v1/items/RiskProject/'+data.record.id+'/archive', {}, function(){
                              $('#tableContainer').jtable('reload');
                           });
                        }, { warning: true });
                     }
                  });
                  
               return retobj;
            }
         },             
         detachanddelete: {
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               if(!data.record.access.update)
                  return "";

               var retobj= $('<a />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .css({cursor: 'pointer'})
                  .text('{{__("Detach risks and delete project"); }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('delete'))
                  .on('click', function(){
                     confirmDialog('{{ __("Detach risks and delete project") }}', '{{ __("The risk project will be deleted but the risks will remain in the risk list as other risks without any relation to the project in which they were created") }}', function(formdata){
                        ajaxPost('/api/v1/items/RiskProject/'+data.record.id+'/detachanddelete', {}, function(){
                              $('#tableContainer').jtable('reload');
                           });
                     }, { warning: true });
                  });
                  
               return retobj;
            }
         },             
         deleteproject: {
            edit: false,
            create: false,
            type: 'command',
            footer: true,
            display: function(data){
               if(!data.record.access.update)
                  return "";

               var retobj= $('<a />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .css({cursor: 'pointer'})
                  .text('{{__("Delete project"); }}')
                  .append($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('delete'))
                  .on('click', function(){
                     confirmDialog('{{ __("Delete project") }}', '{{ __("The risks related to the project will also be deleted") }}', function(formdata){
                        ajaxPost('/api/v1/items/RiskProject/'+data.record.id+'/deleteproject', {}, function(){
                              $('#tableContainer').jtable('reload');
                           });
                     }, { warning: true });
                  });
                  
               return retobj;
            }
         },
      },
      rowLoaded: function(event, data, row) {
         if(data.record.riskcount)
            $(event.target).find('div[data-record-key="'+data.record.id+'"] div[data-jtable-fieldname="risks"] span.material-symbols-rounded').css({'color': 'var(--bs-danger)'});
      },
   });
   $('#tableContainer').jtable('load');
});

</script>

<div id="tableContainer"></div>
<div style="display: none;" id="riskmatrixtemplate">
   <x-risk-matrix template nolinks hideheaders/>
</div>

@endsection
