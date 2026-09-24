@php if(Auth::user()->cannot('index', \App\Models\LibraryDocument::class)) abort(403); @endphp
@extends('layouts.master')
@section('container')
<style>
   #documentVersionTableContainer {
      margin-top: 100px;
   }
</style>
<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("My documents") }}',
      paging: true,
      searchfield: true,
      tableId: 'documentlibrarytable',
      bootstrap: true,
      accordion: true,
      actions: {
@if(Auth::user()->can('index', \App\Models\LibraryDocument::class))
         listAction: '/api/v1/items/LibraryDocument?responsible_user_id={{ auth()->user()->id }}',
@endif
         updateAction: '/api/v1/items/LibraryDocument',
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
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
            listClass: 'd-inline-block col-12 col-md-6',
         },
         hr0: {
            list: true,
            edit: false,
            create: false,
            display: function(data){
               return $('<hr />'); 
            }
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
               if(('ledningssystemet/document' != data.record.contenttype) ||
                  (data.record.responsible_user_id != {{ auth()->user()->id }}))
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

         data.form.find('.jtable-file-input').closest('.jtable-input-field-container').hide();
      },
      recordUpdated: function(event, data){
         $('#tableContainer').jtable('reload');
      },
      recordsLoaded: function(event, data){
      },
   });
   $('#tableContainer').jtable('load');

@if(auth()->user()->can('publishdocument', \App\Models\DocumentVersion::class))
   $('#documentVersionTableContainer').jtable({
      title: '{{ __("Document versions pending approval") }}',
      tableId: 'documentversionstable',
      bootstrap: true,
      accordion: true,
      actions: {
         listAction: '/api/v1/items/DocumentVersion?approver_id={{ auth()->user()->id }}&pending_approval=1',
      },
      fields: {
         id: {
            list: false,
            type: 'hidden',
            key: true,
         },
         name: {
            title: '{{ __('Name') }}',
            list: true,
            header: true,
            display: function(data){
               return data.record.library_document.name + ' v' + data.record.major_version + '.' + data.record.minor_version;
            }
         },
         description: {
            title: '{{ __('Description') }}',
            list: true,
            display: function(data){
               return data.record.library_document.description;
            }
         },
         responsible_user_id: {
            title: '{{ __('Responsible') }}',
            list: true,
            listClass: 'd-inline-block col-12',
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
         editdocument: {
            title: '',
            create: false,
            edit: false,
            list: true,
            width: '1%',
            footer: true,
            display: function(data){
               return $('<a />')
                  .attr('href', '/management/documenteditor/'+data.record.library_document_id)
                  .addClass('btn btn-outline-primary btn-sm')
                  .text('{{ __("Edit document") }}')
                  .prepend($('<span />')
                     .addClass('material-symbols-rounded')
                     .text('edit_note')
                  );
            }
         },
      },
   });
   $('#documentVersionTableContainer').jtable('load');
@endif
});
</script>
<div id="tableContainer"></div>
@if(auth()->user()->can('publishdocument', \App\Models\DocumentVersion::class))
<div id="documentVersionTableContainer"></div>
@endif
@endsection
