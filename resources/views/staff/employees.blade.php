@php if(Auth::user()->cannot('index', \App\Models\Employee::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>

const expiringQualifications = @php echo(\App\Models\Qualification::where('expires', true)->pluck('id')); @endphp;

$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Employees") }}',
      paging: true,
      searchfield: true,
      tableId: 'roleslist',
      bootstrap: true,
      accordion: true,
      filter: {
         hidechecked: {
            type: 'checkbox',
            value: '1',
            checked: false,
            text: '{{ __('Hide items without issues') }}',
         },
      },
      actions: {
         listAction: '/api/v1/items/Employee',
      },
      fields: {
         id: {
            key: true,
            list: false,
         },
         name: {
            title: '{{ __('Name') }}',
            list: true,
            header: true,
         },
         title: {
            title: '{{ __('Title') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         departments: {
            title: '{{ __('Departments') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.departments).forEach((obj) => {
                  retval.append($('<div />').text(obj.name));
               });
               
               return retval;
            }
         },
         enabled: {
            title: '{{ __('Enabled') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />')
                  .text(data.record.enabled ? '{{ __("Yes") }}' : '{{ __("No") }}');
                  
               if(!data.record.enabled)
                  retval.addClass('text-danger');
               
               return retval;
            }
         },
         hr0: {
            list: true,
            display: function(data){
               return $('<hr />'); 
            }
         },
         manager: {
            title: '{{  __("Manager") }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
         },
         directreports: {
            title: '{{ __('Direct reports') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-8',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.directreports).forEach((obj) => {
                  retval.append($('<div />').text(obj.name));
               });
               
               return retval;
            }
         },
         hr1: {
            list: true,
            display: function(data){
               return $('<hr />'); 
            }
         },
         roles: {
            title: '{{ __('Roles') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.roles).forEach((obj) => {
                  retval.append($('<div />').text(obj.name));
               });
               
               return retval;
            }
         },
         ownership: {
            title: '{{ __('Responsible for') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               if(data.record.processes.length)
               {
                  retval.append($('<div />')
                     .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                     .text(data.record.processes.length+' {{ __("processes") }}')
                     .on('click', function(){
                        var content = $('<div />');
                        Object.values(data.record.processes).forEach((obj) => {
                           content.append($('<li />')
                              .text(obj.name));
                        });
                        showDialog('{{ __("Processes") }}', content, { modalClass: 'modal-lg' });
                     }));
               }  
               if(data.record.informationtypes.length)
               {
                  retval.append($('<div />')
                     .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                     .text(data.record.informationtypes.length+' {{ __("information types") }}')
                     .on('click', function(){
                        var content = $('<div />');
                        Object.values(data.record.informationtypes).forEach((obj) => {
                           content.append($('<li />')
                              .text(obj.name));
                        });
                        showDialog('{{ __("Information types") }}', content, { modalClass: 'modal-lg' });
                     }));
               }  

               if(data.record.assets.length)
               {
                  retval.append($('<div />')
                     .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                     .text(data.record.assets.length+' {{ __("assets") }}')
                     .on('click', function(){
                        var content = $('<div />');
                        Object.values(data.record.assets).forEach((obj) => {
                           content.append($('<li />')
                              .text(obj.name));
                        });
                        showDialog('{{ __("Assets") }}', content, { modalClass: 'modal-lg' });
                     }));
               }  

@if(!config('ledningssystemet.disable_supplier'))
               if(data.record.suppliers.length)
               {
                  retval.append($('<div />')
                     .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                     .text(data.record.suppliers.length+' {{ __("suppliers") }}')
                     .on('click', function(){
                        var content = $('<div />');
                        Object.values(data.record.suppliers).forEach((obj) => {
                           content.append($('<li />')
                              .text(obj.name));
                        });
                        showDialog('{{ __("Suppliers") }}', content, { modalClass: 'modal-lg' });
                     }));
               }  
@endif
               if(data.record.controls.length)
               {
                  retval.append($('<div />')
                     .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                     .text(data.record.controls.length+' {{ __("controls") }}')
                     .on('click', function(){
                        var content = $('<div />');
                        Object.values(data.record.controls).forEach((obj) => {
                           content.append($('<li />')
                              .text(obj.name));
                        });
                        showDialog('{{ __("Controls") }}', content, { modalClass: 'modal-lg' });
                     }));
               }  

               return retval;
            }
         },
         authorities: {
            title: '{{ __('Authorities') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-4',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.roles).forEach((obj) => {
                  if(obj.authorities)
                  {
                     retval.append($('<div />')
                        .append($('<span />')
                           .addClass('authority-header')
                           .text('{{ __("Through role") }} '+obj.name))
                        .append($('<div />')
                           .addClass('authority-description')
                           .text(obj.authorities)));
                  }                           
               });
               
               return retval;
            }
         },
         hr2: {
            list: true,
            display: function(data){
               return $('<hr />'); 
            }
         },
         processaccountabilities: {
            title: '{{ __('Accountable for') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.processaccountabilities).forEach((obj) => {
                  retval.append($('<div />')
                     .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                     .text(obj.activities.length+' {{ __("tasks in process") }} '+obj.name)
                     .on('click', function(){
                        var content = $('<div />');
                        Object.values(obj.activities).forEach((activity) => {
                           content.append($('<li />')
                              .text(activity.name)
                              .append($('<span />')
                                 .addClass('rolenote')
                                 .text('({{ __("Role") }} '+activity.role.name+')')));
                        });
                        showDialog(obj.name, content, { modalClass: 'modal-lg' });
                     }));
               });
               return retval;
            }
         },
         processresponsibilities: {
            title: '{{ __('Responsible for') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.processresponsibilities).forEach((obj) => {
                  retval.append($('<div />')
                     .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                     .text(obj.activities.length+' {{ __("tasks in process") }} '+obj.name)
                     .on('click', function(){
                        var content = $('<div />');
                        Object.values(obj.activities).forEach((activity) => {
                           content.append($('<li />')
                              .text(activity.name)
                              .append($('<span />')
                                 .addClass('rolenote')
                                 .text('({{ __("Role") }} '+activity.role.name+')')));
                        });
                        showDialog(obj.name, content, { modalClass: 'modal-lg' });
                     }));
               });
               return retval;
            }
         },
         hr3: {
            list: true,
            display: function(data){
               return $('<hr />'); 
            }
         },
         qualifications: {
            title: '{{ __('Expected qualifications') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.qualifications).forEach((role) => {
                  var rolecontainer = $('<div />')
                     .append($('<span />')
                           .addClass('authority-header')
                           .text('{{ __("Through role") }} '+role.name))
                     .appendTo(retval);
                  Object.values(role.qualifications).forEach((obj) => {
                     var qual = $('<div />')
                        .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                        .text(obj.name+' ('+(obj.mandatory ? '{{ __("Mandatory") }}, ' : '')+'{{__("performed") }}: '+(obj.finished_at ? obj.finished_at : '{{ __("never") }}')+', {{__("expires") }}: '+(obj.expires_at ? obj.expires_at : '{{ __("never") }}')+')')
                        .on('click', function(){
                           showDialog(obj.name, obj.description, { modalClass: 'modal-lg' });
                        })
                        .appendTo(rolecontainer);
                        
                     if(obj.mandatory && !obj.finished_at)
                        qual.addClass('text-danger');
                     
                     if(obj.expires_at && ((new Date(obj.expires_at).getTime()) < (new Date('{{ date("Y-m-d") }}').getTime())))
                        qual.addClass('text-danger');
                     
                     if(obj.expires_at && ((new Date(obj.expires_at).getTime()) < (new Date('{{ date("Y-m-d", strtotime("+1 MONTHS")) }}').getTime())))
                        qual.addClass('text-warning');
                     
                  });
               });
               return retval;
            }
         },
         competences: {
            title: '{{ __('Expected competences') }}',
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            display: function(data)
            {
               var retval = $('<div />');
               Object.values(data.record.competences).forEach((role) => {
                  var rolecontainer = $('<div />')
                     .append($('<span />')
                           .addClass('authority-header')
                           .text('{{ __("Through role") }} '+role.name))
                     .appendTo(retval);
                  Object.values(role.competences).forEach((obj) => {
                     var qual = $('<div />')
                        .css({'cursor': 'pointer', 'text-decoration': 'underline dotted' })
                        .text(obj.name + ' ({{ __("Evaluated") }}: '+((null == obj.achieved_level) ? '{{ __("never") }}' : (new Date(obj.achieved_level.updated_at).toISOString().substr(0,10)))+')')
                        .on('click', function(){
                           showDialog(obj.name, obj.description, { modalClass: 'modal-lg' });
                        })
                        .appendTo(rolecontainer);

                     if(!obj.achieved_level)
                        qual.addClass('text-danger');
                     

                  });
               });
               return retval;
            }
         },         
         hr4: {
            list: true,
            display: function(data){
               return $('<hr />'); 
            }
         },
@if(auth()->user()->can('index', \App\Models\QualificationUser::class))         
         qualificationslist: {
            title: '',
            type: 'command',
            width: '1%',
            footer: true,
            display: function (sourcedata) {
               var retobj = $('<button />')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __('Qualifications') }}')
                  .prepend($('<span>license</span>')
                     .addClass('material-symbols-rounded'));
                  
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#qualificationstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                   $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Qualifications") }}',
                        tableId: 'qualificationstable',
                        actions: {
                           listAction: '/api/v1/items/QualificationUser?user_id='+sourcedata.record.id,
                           createAction:  '/api/v1/items/QualificationUser',
                           updateAction:  '/api/v1/items/QualificationUser',
                           deleteAction:  '/api/v1/items/QualificationUser',
                        },
                        messages: {
                           addNewRecord: '{{ __('Add qualification') }}',
                        },
                        fields: {
                           user_id: {
                              type: 'hidden',
                              defaultValue: sourcedata.record.id,
                              create: true,
                              edit: true,
                           },
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                              create: false,
                           },
                           qualification_id: {
                              title: '{{ __("Qualification") }}',
                              edit: false,
                              create: true,
                              list: true,
                              width: '55%',
                              options: [
@foreach(\App\Models\Qualification::orderBy('name')->get()->each->setAppends([]) as $obj)
                                 { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach                              
                              ]
                           },
                           finished_at: {
                              title: '{{ __("Performed") }}',
                              edit: true,
                              create: true,
                              list: true,
                              type: 'date',
                              defaultValue: null,
                              width: '15%',
                           },
                           expires_at: {
                              title: '{{ __("Expires") }}',
                              edit: true,
                              create: true,
                              list: true,
                              type: 'date',
                              defaultValue: null,
                              width: '15%',
                           },
                           planned_at: {
                              title: '{{ __("Planned") }}',
                              edit: true,
                              create: true,
                              list: true,
                              type: 'date',
                              defaultValue: null,
                           },
                           verificatefile: {
                              title: '{{ __('File') }}',
                              type: 'file',
                              accept: '',
                              list: false,
                              edit: true,
                              create: true,
                           },                           
                           download: {
                              title: '',
                              create: false,
                              edit: false,
                              list: true,
                              width: '1%',
                              footer: true,
                              display: function(data){
                                 if(data.record.filename)
                                 {
                                    return $('<a />')
                                       .attr('href', '/api/v1/items/QualificationUser/'+data.record.id+'/download')
                                       .addClass('material-symbols-rounded')
                                       .text('download');
                                 }
                                 else
                                    return '';
                              }
                           },   
                        },
                        recordUpdated: function(event, data) {
                           $('#tableContainer').jtable('reloadRow', $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"]'), function(){
                              $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"] div[data-jtable-fieldname="qualificationslist"] button').click();
                           });
                        },
                        recordAdded: function(event, data) {
                           $('#tableContainer').jtable('reloadRow', $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"]'), function(){
                              $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"] div[data-jtable-fieldname="qualificationslist"] button').click();
                           });
                        },
                        formCreated: function(event, data){
                           $(data.form).find('#Edit-qualification_id').on('change', function(){
                              var selectedId = $(this).val();
                              if(null !== selectedId)
                              {
                                 if(expiringQualifications.includes(parseInt(selectedId)))
                                    $(this).closest('form').find('.jtable-input-field-container:has(#Edit-expires_at)').show();
                                 else
                                    $(this).closest('form').find('.jtable-input-field-container:has(#Edit-expires_at)').hide();
                              }                              
                           }).trigger('change');
                        },
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },
@endif
         competenceslist: {
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
                  .text('{{ __('Competence evaluation') }}')
                  .prepend($('<span>school</span>')
                     .addClass('material-symbols-rounded'));
                  
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#competencesstable').length)
                  {
                     $('#tableContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                   $('#tableContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Competence evaluation") }}',
                        tableId: 'competencesstable',
                        paging: true,
                        bootstrap: true,
                        filter: {
                           usermandatoryonly: {
                              type: 'checkbox',
                              value: '1',
                              default: true,
                              text: '{{ __('Show mandatory competences only') }}',
                           },
                        },
                        messages: {
                           editRecord: '{{ __('Re-assess competence') }}',
                        },
                        actions: {
@if(Auth::user()->can('index', \App\Models\CompetenceLevel::class))
                           listAction: '/api/v1/items/Competence?user_id='+sourcedata.record.id,
@endif         
@if(Auth::user()->can('update', \App\Models\CompetenceLevel::class))
                           updateAction:  '/api/v1/items/Competence',
@endif         
                        },
                        fields: {
                           user_id: {
                              type: 'hidden',
                              defaultValue: sourcedata.record.id,
                              edit: true,
                           },
                           id: {
                              key: true,
                              list: false,
                              edit: false,
                           },
                           name: {
                              title: '',
                              edit: false,
                              list: true,
                              header: true,
                           },
                           description: {
                              title: '{{ __("Description") }}',
                              list: true,
                              edit: false,
                              listClass: 'd-inline-block col-12 col-md-4',
                           },
                           acceptable_level: {
                              title: '{{ __("Acceptable level") }}',
                              edit: false,
                              list: true,
                              listClass: 'd-inline-block col-12 col-md-4',
                           },
                           desired_level: {
                              title: '{{ __("Desired level") }}',
                              edit: false,
                              list: true,
                              listClass: 'd-inline-block col-12 col-md-4',
                           },
                           hr0: {
                              title: '',
                              edit: false,
                              list: true,
                              display: function(data){
                                 return '<hr />';
                              }
                           },
                           evaluated: {
                              title: '{{ __("Evaluated") }}',
                              list: true,
                              edit: false,
                              listClass: 'd-inline-block col-12 col-md-4',
                           },
                           achieved_level: {
                              title: '{{ __("Actual level") }}',
                              edit: true,
                              list: true,
                              required: true,
                              listClass: 'd-inline-block col-12 col-md-4',
                              options: function(data){

                                 return '/api/v1/items/CompetenceLevel?competence_id='+data.record.id;
                              }
                           },
                           note: {
                              title: '{{ __("Evaluation notes") }}',
                              edit: true,
                              list: true,
                              listClass: 'd-inline-block col-12 col-md-4',
                              type: 'textarea',
                           },
                           hr1: {
                              title: '',
                              edit: false,
                              list: true,
                              display: function(data){
                                 return '<hr />';
                              }
                           },
@if(Auth::user()->can('update', \App\Models\Competence::class))         
                           clearassessment: {
                              sorting: false,
                              edit: false,
                              create: false,
                              footer: true,
                              listClass: 'd-inline-block',
                              display: function(data){
                                 if(!data.record.achieved_level)
                                    return '';
                                 
                                 return $('<button />')
                                    .addClass('btn btn-outline-primary btn-sm')
                                    .text('{{ __('Clear assessment') }}')
                                    .prepend($('<span>delete</span>')
                                       .addClass('material-symbols-rounded'))
                                    .click(function(){ 
                                       confirmDialog('{{ __("Clear assessment") }}', '{{ __("The current assessment will be removed") }}', function(){
                                             ajaxPost('/api/v1/items/Competence/'+data.record.id+'/clearevaluation?user_id='+data.record.user_id, {}, function(){
                                                   $('#tableContainer').jtable('reloadRow', $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"]'), function(){
                                                      $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"] div[data-jtable-fieldname="competenceslist"] button').click();
                                                   });
                                                });                        
                                       });
                                    });
                                 }   
                           },
@endif
                        },
                        recordUpdated: function(event, data) {
                           $('#tableContainer').jtable('reloadRow', $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"]'), function(){
                              $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"] div[data-jtable-fieldname="competenceslist"] button').click();
                           });
                        },
                        recordAdded: function(event, data) {
                           $('#tableContainer').jtable('reloadRow', $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"]'), function(){
                              $('#jtable-body-roleslist > .jtable-data-row[data-record-key="'+sourcedata.record.id+'"] div[data-jtable-fieldname="competenceslist"] button').click();
                           });
                        },
                        
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
   
@endsection
