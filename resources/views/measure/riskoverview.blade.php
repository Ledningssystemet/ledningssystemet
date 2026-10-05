<?php

if(Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\Risk::class))
   abort(403);

if(request()->has('dataset'))
{
   switch(request()->input('dataset'))
   {
      case 'items':
         die(json_encode(['config' => [
            'type' => 'radar',
            'options' => [
               'elements' => [
                  'line' => [
                     'borderWidth' => 1,
                  ],
               ],
               'scales' => [
                  'r' => [
                     'min' => 0,
                     'max' => 100,
                  ]
               ],
            ],
            'data' => [
               'labels' => [
                  __("Departments"),
                  __("Processes"),
                  __("Tasks"),
                  __("Information types"),
                  __("Assets"),
                  __("Suppliers"),
                  __("Customers")
               ],
               'datasets' =>  [
                  [
                     'label' => __("Accumulated"),
                     'data' => [
                        (\Ledningssystemet\Ledningssystemet\Models\Department::count() == 0) ? 0 : round(100*(\Ledningssystemet\Ledningssystemet\Models\Risk::whereNull('replacedby_id')->whereIn('context_type', [\Ledningssystemet\Ledningssystemet\Models\Department::class])->whereIn('context_id', \Ledningssystemet\Ledningssystemet\Models\Department::pluck('id'))->select('context_id')->distinct()->count('context_id')/\Ledningssystemet\Ledningssystemet\Models\Department::count())),
                        (\Ledningssystemet\Ledningssystemet\Models\Process::count() == 0) ? 0 : round(100*(\Ledningssystemet\Ledningssystemet\Models\Risk::whereNull('replacedby_id')->whereIn('context_type', [\Ledningssystemet\Ledningssystemet\Models\Process::class])->whereIn('context_id', \Ledningssystemet\Ledningssystemet\Models\Process::pluck('id'))->select('context_id')->distinct()->count('context_id')/\Ledningssystemet\Ledningssystemet\Models\Process::count())),
                        (\Ledningssystemet\Ledningssystemet\Models\ProcessActivity::count() == 0) ? 0 : round(100*(\Ledningssystemet\Ledningssystemet\Models\Risk::whereNull('replacedby_id')->whereIn('context_type', [\Ledningssystemet\Ledningssystemet\Models\ProcessActivity::class])->whereIn('context_id', \Ledningssystemet\Ledningssystemet\Models\ProcessActivity::pluck('id'))->select('context_id')->distinct()->count('context_id')/\Ledningssystemet\Ledningssystemet\Models\ProcessActivity::count())),
                        (\Ledningssystemet\Ledningssystemet\Models\InformationType::count() == 0) ? 0 : round(100*(\Ledningssystemet\Ledningssystemet\Models\Risk::whereNull('replacedby_id')->whereIn('context_type', [\Ledningssystemet\Ledningssystemet\Models\InformationType::class])->whereIn('context_id', \Ledningssystemet\Ledningssystemet\Models\InformationType::pluck('id'))->select('context_id')->distinct()->count('context_id')/\Ledningssystemet\Ledningssystemet\Models\InformationType::count())),
                        (\Ledningssystemet\Ledningssystemet\Models\Asset::count() == 0) ? 0 : round(100*(\Ledningssystemet\Ledningssystemet\Models\Risk::whereNull('replacedby_id')->whereIn('context_type', [\Ledningssystemet\Ledningssystemet\Models\Asset::class])->whereIn('context_id', \Ledningssystemet\Ledningssystemet\Models\Asset::pluck('id'))->select('context_id')->distinct()->count('context_id')/\Ledningssystemet\Ledningssystemet\Models\Asset::count())),
                        (\Ledningssystemet\Ledningssystemet\Models\Supplier::count() == 0) ? 0 : round(100*(\Ledningssystemet\Ledningssystemet\Models\Risk::whereNull('replacedby_id')->whereIn('context_type', [\Ledningssystemet\Ledningssystemet\Models\Supplier::class])->whereIn('context_id', \Ledningssystemet\Ledningssystemet\Models\Supplier::pluck('id'))->select('context_id')->distinct()->count('context_id')/\Ledningssystemet\Ledningssystemet\Models\Supplier::count())),
                        (\Ledningssystemet\Ledningssystemet\Models\Customer::count() == 0) ? 0 : round(100*(\Ledningssystemet\Ledningssystemet\Models\Risk::whereNull('replacedby_id')->whereIn('context_type', [\Ledningssystemet\Ledningssystemet\Models\Customer::class])->whereIn('context_id', \Ledningssystemet\Ledningssystemet\Models\Customer::pluck('id'))->select('context_id')->distinct()->count('context_id')/\Ledningssystemet\Ledningssystemet\Models\Customer::count())),
                     ],
                     'fill' => true,
                     'backgroundColor' => '#ced4da30',
                     'borderColor' => '#ced4da',
                     'pointBackgroundColor' => '#ced4da',
                     'pointBorderColor' => '#fff',
                     'pointHoverBackgroundColor' => '#fff',
                     'pointHoverBorderColor' => '#ced4da'
                  ],
               ]
            ]
         ]
      ]));
      break;

      default:
         abort(404);
   }
}
?>

@extends('layouts.master')

@section('container')


<style type="text/css">
   select#overviewtype {
      max-width: 400px;
   }

   #dataview {
      max-width: 750px;
   }
</style>
<script>
$(function(){
   var chart = null;
   $('#overviewtype').on('change', function(){
      var dataset = $(this).val();
      ajaxGet('{{ request()->url() }}?dataset='+dataset, function(data){
         var config = data['config'];
         switch(dataset){
            case 'items':
               config.options.scales.r.ticks = {stepSize: 25, callback: function(value, index, ticks) {
                  return value + ' %';
               }};
               break;
         }
         $('#graphcanvas').empty();
         
         if(null != chart)
            chart.destroy();
         
         chart = new Chart($('#graphcanvas'), config);
         $('#dataview').addClass('show');
      });
   }).trigger('change');
    
});
</script>
<h1>{{ __("Risk overview") }}</h1>   
<div class="mt-4 mb-4">
   <label class="form-label" for="overviewtype">{{ __("Show") }}</label>
   <select id="overviewtype" class="form-select">
      <option value="items" selected="selected">{{ __("Context coverage") }}</option>
   </select>
</div>
<div id="dataview" class="mt-4 mb-4">
   <canvas id="graphcanvas"></canvas>

</div>
@endsection
