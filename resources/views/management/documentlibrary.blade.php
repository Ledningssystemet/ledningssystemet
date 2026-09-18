@php if(Auth::user()->cannot('create', \App\Models\LibraryDocument::class)) abort(403); @endphp
@extends('layouts.master')
@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("Document library") }}',
      paging: true,
      searchfield: true,
      tableId: 'documentlibrarytable',
      bootstrap: true,
      accordion: true,
      messages: {
         addNewRecord: '{{ __('Create new document') }}',
      },
      filter: {},
      actions: {
         listAction: '/api/v1/items/LibraryDocument',
@if(Auth::user()->can('create', \App\Models\LibraryDocument::class))
         createAction: '/api/v1/items/LibraryDocument',
@endif
@if(Auth::user()->can('update', \App\Models\LibraryDocument::class))
         updateAction: '/api/v1/items/LibraryDocument',
@endif
@if(Auth::user()->can('delete', \App\Models\LibraryDocument::class))
         deleteAction: '/api/v1/items/LibraryDocument',
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
            maxlength: 255,
            header: true,
         },
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            create: true,
            edit: true,
            list: true,
            listClass: 'd-inline-block col-12 col-md-6',
            defaultValue: {{ auth()->user()->id }},
            options: [
               { Value: null, DisplayText: '{{ __("None assigned") }}' },
                  @foreach(App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
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
            required: true,
         },
         hr1: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         filetype: {
            title: '{{ __("Document type") }}',
            type: 'select',
            list: false,
            edit: false,
            create: true,
            options: [
               { Value: 'document', DisplayText: '{{ __("Document") }}' },
               { Value: 'file', DisplayText: '{{ __("Uploaded file") }}' },
            ]
         },
         file: {
            title: '{{ __('File') }}',
            type: 'file',
            accept: '',
            list: false,
            edit: true,
            create: true,
         },
         processes: {
            title: '{{ __('Processes') }}',
            type: 'select',
            list: true,
            edit: true,
            create: false,

            multiple: true,
            options: [
@foreach(\App\Models\Process::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: '{{ $obj->name }}' },
@endforeach               
            ]
         },
         hr2: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
         },
         editdocument: {
            title: '',
            create: false,
            edit: false,
            list: true,
            width: '1%',
            footer: true,
            display: function(data){
               if('ledningssystemet/document' != data.record.contenttype)
                  return $('<span />');

               return $('<a />')
                  .attr('href', '/management/documenteditor/'+data.record.id)
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __("Edit document") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('edit_note')
                  );
            }
         },
         download: {
            title: '',
            create: false,
            edit: false,
            list: true,
            width: '1%',
            footer: true,
            display: function(data){
               if(0 == data.record.contentlength)
                  return $('<span />');

               return $('<a />')
                  .attr('href', '/api/v1/LibraryDocument/'+data.record.id+'/download')
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __("Download") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('download')
                  );
            }
         },         
      },
      formCreated: function(event, data){
         data.form.find('#Edit-filetype').on('change', function(){
            if($(this).val() != 'file')
               data.form.find('.jtable-file-input').closest('.jtable-input-field-container').hide();
            else
               data.form.find('.jtable-file-input').closest('.jtable-input-field-container').show();
         });

         if(!data.record || ('ledningssystemet/document' == data.record.contenttype))
            data.form.find('.jtable-file-input').closest('.jtable-input-field-container').hide();
      },
      recordUpdated: function(event, data){
         $('#tableContainer').jtable('reload');
      },
      recordsLoaded: function(event, data){
      },
   });
   $('#tableContainer').jtable('load');
});
</script>
<div id="tableContainer"></div>
   
@endsection
