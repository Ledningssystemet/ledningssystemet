<?php
   $props = App\Models\CustomProperty::where('context', $classname)->orderBy('ordinal')->get()->each->setAppends([]);
   $showFilter = $showFilter ?? false;
?>
@if($showFilter)
@foreach(App\Models\CustomProperty::where('context', $classname)->whereNotIn('type', ['string', 'textarea'])->get()->each->setAppends([]) as $obj)
customproperty_{{$obj->id}}: {
   type: 'select',
   text: '{{ $obj->name }}',
   default: 0,
   options: [
      { value: 0, text: '{{ __('Show all') }}' },
@foreach(json_decode($obj->getJtableOptions()) as $option)
@if(0 < intval($option->Value))
      { value: {{ $option->Value }}, text: '{{ $option->DisplayText }}' },
@endif
@endforeach
   ]
},
@endforeach
@else
@foreach($props as $obj)
hr_@php echo(str_replace('-', '', \Illuminate\Support\Str::uuid())); @endphp: {
   list: true,
   edit: false,
   create: false,
   display: function(data){
   return $('<hr />');
   }
},
customproperty_{{$obj->id}}: {
   title: '{{ __($obj->name) }}',
   type: '{{ $obj->getJtableType() }}',
   create: false,
   edit: {{ $obj->user_editable ? 'true' : 'false' }},
   list: {{ $obj->display_on_card ? 'true' : 'false' }},
   required: {{ $obj->required ? 'true' : 'false' }},
   options: {!! $obj->getJtableOptions() !!},
},
@endforeach
@endif