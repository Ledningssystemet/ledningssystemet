@php if(Auth::user()->cannot('index', \App\Models\EmployeeRole::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Roles") }}',
      paging: true,
      searchfield: true,
      tableId: 'roleslist',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create role') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\EmployeeRole::class))         
         listAction: '/api/v1/items/EmployeeRole',
@endif
@if(Auth::user()->can('update', \App\Models\EmployeeRole::class))         
         updateAction: '/api/v1/items/EmployeeRole',
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
            list: true,
            create: false,
            edit: false,
         },
         role_users: {
            title: '{{ __('Users') }}',
            list: true,
            create: false,
            edit: false,
            display: function(data) {
               var retval = $('<div />');
               Object.values(data.record.role_users).forEach((obj) => {
                  retval.append( $('<div />')
                     .text(obj.name)
                  );
               });
               
               return retval;
            }
         },
         hr1: {
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
            create: false,
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
         authorities: {
            title: '{{ __('Authorities') }}',
            type: 'textarea',
            create: false,
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
         qualifications: {
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
@if(Auth::user()->can('index', \App\Models\Qualification::class))         
                           listAction: '/api/v1/items/QualificationRole?role_id='+sourcedata.record.id,
@endif         
@if(Auth::user()->can('create', \App\Models\Qualification::class))         
                           createAction:  '/api/v1/items/QualificationRole',
@endif         
@if(Auth::user()->can('update', \App\Models\Qualification::class))         
                           updateAction:  '/api/v1/items/QualificationRole',
@endif         
@if(Auth::user()->can('delete', \App\Models\Qualification::class))         
                           deleteAction:  '/api/v1/items/QualificationRole',
@endif         
                        },
                        messages: {
                           addNewRecord: '{{ __('Add qualification') }}',
                        },
                        fields: {
                           role_id: {
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
                              required: true,
                              options: [
@foreach(\App\Models\Qualification::orderBy('name')->get()->each->setAppends([]) as $obj)
                                 { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach                              
                              ]
                           },
                           mandatory: {
                              title: '{{ __("Mandatory") }}',
                              edit: true,
                              create: true,
                              list: true,
                              width: '20%',
                              options: {
                                 0: '{{ __("No") }}',
                                 1: '{{ __("Yes") }}',
                              }
                           },
                        },
                        formCreated: function(event, data){
                        },
                     }, function (data) {
                            data.childTable.jtable('load');
                     }
                   );
               });
                  
               return retobj;
            }
         },
         competences: {
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
                  .text('{{ __('Competences') }}')
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
                        title: '{{__("Competences") }}',
                        tableId: 'competencesstable',
                        actions: {
@if(Auth::user()->can('index', \App\Models\RoleCompetence::class))         
                           listAction: '/api/v1/items/RoleCompetence?role_id='+sourcedata.record.id,
@endif         
@if(Auth::user()->can('create', \App\Models\RoleCompetence::class))         
                           createAction:  '/api/v1/items/RoleCompetence',
@endif         
@if(Auth::user()->can('update', \App\Models\RoleCompetence::class))         
                           updateAction:  '/api/v1/items/RoleCompetence',
@endif         
@if(Auth::user()->can('delete', \App\Models\RoleCompetence::class))         
                           deleteAction:  '/api/v1/items/RoleCompetence',
@endif         
                        },
                        messages: {
                           addNewRecord: '{{ __('Add competence') }}',
                        },
                        fields: {
                           role_id: {
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
                           competence_id: {
                              title: '{{ __("Competence") }}',
                              edit: false,
                              create: true,
                              list: true,
                              required: true,
                              options: [
@foreach(\App\Models\Competence::orderBy('name')->get()->each->setAppends([]) as $obj)
                                 { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach                              
                              ]
                           },
                           acceptable_competence_level_id: {
                              title: '{{ __("Acceptable competence level") }}',
                              edit: true,
                              create: true,
                              list: true,
                              required: true,
                              dependsOn: 'competence_id',
                              options: function(data){
                                 var competence_id = null;
                                 if(data.record && data.record.competence_id)
                                    competence_id = data.record.competence_id;
                                 
                                 if(data.dependedValues.competence_id)
                                    competence_id = data.dependedValues.competence_id;
                                 return '/api/v1/items/CompetenceLevel?competence_id='+competence_id;
                              }
                           },
                           desired_competence_level_id: {
                              title: '{{ __("Desired competence level") }}',
                              edit: true,
                              create: true,
                              list: true,
                              required: true,
                              dependsOn: 'competence_id',
                              options: function(data){
                                 var competence_id = null;
                                 if(data.record && data.record.competence_id)
                                    competence_id = data.record.competence_id;

                                 if(data.dependedValues.competence_id)
                                    competence_id = data.dependedValues.competence_id;
                                 return '/api/v1/items/CompetenceLevel?competence_id='+competence_id;
                              }
                           }
                        },
                        formCreated: function(event, data){
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
