@php if(Auth::user()->cannot('index', \App\Models\ActivityFlow::class)) abort(403); @endphp
<?php
   $activityflowtemplate = \App\Models\ActivityFlowTemplate::findOrFail(request()->input('activity_flow_template_id', 0));
   
   // Ensure that user have sufficient privileges
   if(!($activityflowtemplate->user_instantiatable || auth()->user()->canAny(['managementtools.edit'])))
      abort(403);
?>
@extends('layouts.master')

@section('container')
<style>
#flowitems {
   margin: 40px 0 40px 0;
   padding: 20px 0 20px 0;
   width: 100%;
}

th {
   text-align: left;
   padding-left: 10px;
}

.form-control {
   font-size: 0.8em;
}

.verticalseparator {
   height: 1em;
}

.headerrow td span{
   display: block;
   text-align: center;
   font-size: 1.2em;
   background-color: var(--bs-gray-700);
   color: white;
}

.itemrow {
   vertical-align: top;
}

.itemrow:nth-child(2n+1) {
   background-color: #f5f5f5;
}

.itemrow td{
   padding: 10px;
   text-align: left;
}

#flowname, #flowdescription{
   max-width: 500px;
}

</style>

<script>
function startFlow()
{
   var data = $('form').serializeArray();
   confirmDialog('{{ __("Start activity flow") }}', '{{ __("Are you sure that you want to start this flow? Activities will be created and distributed to the assignees.") }}', function(){
      ajaxPost('/api/v1/items/ActivityFlowTemplate/{{ $activityflowtemplate->id }}/start', data, function(){
         if(document.referrer)
            window.location=document.referrer;
         else
            window.location='/';
      });
   });
   
   return false;
}
</script>
<h1>{{ __("Start new activity flow") }} {{ $activityflowtemplate->name }}</h1>
<form method="POST" onSubmit="return startFlow();">
   <div class="mb-4 mt-4">
      <label class="form-label" for="flowname">{{ __("Name") }}</label>
      <input class="form-control" id="flowname" name="name" value="{{ $activityflowtemplate->name }}" maxlength="255" required />
   </div>
   <div class="mb-4 mt-4">
      <label class="form-label" for="flowdescription">{{ __("Description") }}</label>
      <textarea class="form-control" id="flowdescription" name="description">{{ $activityflowtemplate->description }}</textarea>
   </div>

<table id="flowitems">
   <tr>
      <th>{{ __("Activity name") }}</th>
      <th>{{ __("Description") }}</th>
      <th>{{ __("Due") }}</th>
      <th>{{ __("Responsible user") }}</th>
   </tr>
      
<?php
   $isfirstitem = true;
?>
@foreach($activityflowtemplate->int_activity_flow_template_items()->orderBy('ordinal')->get()->each->setAppends([]) as $item)
@if("header" == $item->type)
   <tr class="headerrow"><td colspan="4"><div class="verticalseparator"></div><span>{{ $item->name }}</span></td></tr>
@elseif("item" == $item->type)
   <tr class="itemrow">
      <input type="hidden" name="activity_flow_template_item_id[{{ $item->id }}]" value="{{ $item->id }}" />
      <td class="activityname">{{ $item->name }}</td>
      <td>
         <textarea class="form-control" name="itemdescription[{{ $item->id }}]" required>{{ $item->description }}</textarea>
      </td>
      <td class="due">
<?php
if($item->waitforpreceeding && !$isfirstitem)
   echo($item->dueoffsetdays." ".__("days from previous activity being finished"));
else
{
?>         
         <input type="date" class="form-control" name="itemdue[{{ $item->id }}]" value="@php echo(date("Y-m-d", strtotime("+".$item->dueoffsetdays." DAYS"))); @endphp" required />
<?php            
            $isfirstitem = false;
}
?>      
      </td>
      <td>
         <select class="form-control form-select" name="itemresponsible_user_id[{{ $item->id }}]" required>
@foreach(\App\Models\User::where('enabled', true)->get()->each->setAppends([]) as $obj)
            <option value="{{ $obj->id }}" @php echo($obj->id==auth()->user()->id ? " selected=\"selected\"" : ""); @endphp>{{ $obj->name }}</option>

@endforeach         
         </select>
      </td>
   </tr>
@endif
@endforeach
   <tr>
      <td colspan=4>
         <button class="btn btn-sm btn-primary text-light mt-4">
            <span class="material-symbols-rounded">play_circle</span>
            {{ __("Start flow") }}
         </button>
      </td>
   </tr>
</table>
</form>
@endsection
