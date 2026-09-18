@php if(Auth::user()->cannot('index', \App\Models\PersonalAccessToken::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
$(function(){
   $('#tableContainer').jtable({
      title: '{{ __("API tokens") }}',
      paging: true,
      searchfield: true,
      tableId: 'tokenslist',
      messages: {
         addNewRecord: '{{ __('Create API token') }}',
         search: '{{ __('Search by name') }}',
      },
      actions: {
@if(Auth::user()->can('index', \App\Models\PersonalAccessToken::class))
         listAction: '/api/v1/items/PersonalAccessToken',
@endif
@if(Auth::user()->can('create', \App\Models\PersonalAccessToken::class))
         createAction: function(data){
            var name = data.get('name');
            var user_id = data.get('user_id');
            ajaxGet('/api/v1/items/User/'+user_id+'/issuetoken?name='+name, function(response){
               var message = $('<div />')
                  .text('{{ __("A new token has been created, and it will be displayed only this time. A new token needs to be created if this gets lost.") }}');
               
               message.append($('<div />')
                  .text(response.plainTextToken)
                  .css({ 'font-weight': 'bold', 'margin-top': '20px', 'font-size': '12px'}));
                  
               showDialog('{{ __("API token")}}', message, function(){$('#tableContainer').jtable('reload');});
            });    

            return [];
         },
@endif
@if(Auth::user()->can('update', \App\Models\PersonalAccessToken::class))
         updateAction: '/api/v1/items/PersonalAccessToken',
@endif
@if(Auth::user()->can('delete', \App\Models\PersonalAccessToken::class))
         deleteAction: '/api/v1/items/PersonalAccessToken',
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
            width: '50%',
         },
         user_id: {
            title: '{{ __('User') }}',
            create: true,
            edit: false,
            list: true,
            required: true,
            width: '50%',
            options: [
@foreach(\App\Models\User::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach               
            ]
         },
      },
   });
   $('#tableContainer').jtable('load');      
});
</script>
<div id="tableContainer"></div>
<div style="margin-top: 30px;">
   <a href="/systemadmin/swagger">{{ __("To the API documentation") }}</a>
</div>   
@endsection
