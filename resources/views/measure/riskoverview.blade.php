<?php

if(Auth::user()->cannot('index', \App\Models\Risk::class))
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
                        (\App\Models\Department::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->where('context_type', 'App\\Models\\Department')->whereIn('context_id', \App\Models\Department::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Department::count())),
                        (\App\Models\Process::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->where('context_type', 'App\\Models\\Process')->whereIn('context_id', \App\Models\Process::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Process::count())),
                        (\App\Models\ProcessActivity::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->where('context_type', 'App\\Models\\ProcessActivity')->whereIn('context_id', \App\Models\ProcessActivity::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\ProcessActivity::count())),
                        (\App\Models\InformationType::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->where('context_type', 'App\\Models\\InformationType')->whereIn('context_id', \App\Models\InformationType::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\InformationType::count())),
                        (\App\Models\Asset::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->where('context_type', 'App\\Models\\Asset')->whereIn('context_id', \App\Models\Asset::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Asset::count())),
                        (\App\Models\Supplier::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->where('context_type', 'App\\Models\\Supplier')->whereIn('context_id', \App\Models\Supplier::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Supplier::count())),
                        (\App\Models\Customer::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->where('context_type', 'App\\Models\\Customer')->whereIn('context_id', \App\Models\Customer::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Customer::count())),
                     ],
                     'fill' => true,
                     'backgroundColor' => '#ced4da30',
                     'borderColor' => '#ced4da',
                     'pointBackgroundColor' => '#ced4da',
                     'pointBorderColor' => '#fff',
                     'pointHoverBackgroundColor' => '#fff',
                     'pointHoverBorderColor' => '#ced4da'
                  ],
                  [
                     'label' => __("Manually identified"),
                     'data' => [
                        (\App\Models\Department::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNull('partner_object_uid')->where('context_type', 'App\\Models\\Department')->whereIn('context_id', \App\Models\Department::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Department::count())),
                        (\App\Models\Process::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNull('partner_object_uid')->where('context_type', 'App\\Models\\Process')->whereIn('context_id', \App\Models\Process::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Process::count())),
                        (\App\Models\ProcessActivity::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNull('partner_object_uid')->where('context_type', 'App\\Models\\ProcessActivity')->whereIn('context_id', \App\Models\ProcessActivity::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\ProcessActivity::count())),
                        (\App\Models\InformationType::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNull('partner_object_uid')->where('context_type', 'App\\Models\\InformationType')->whereIn('context_id', \App\Models\InformationType::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\InformationType::count())),
                        (\App\Models\Asset::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNull('partner_object_uid')->where('context_type', 'App\\Models\\Asset')->whereIn('context_id', \App\Models\Asset::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Asset::count())),
                        (\App\Models\Supplier::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNull('partner_object_uid')->where('context_type', 'App\\Models\\Supplier')->whereIn('context_id', \App\Models\Supplier::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Supplier::count())),
                        (\App\Models\Customer::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNull('partner_object_uid')->where('context_type', 'App\\Models\\Customer')->whereIn('context_id', \App\Models\Customer::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Customer::count())),
                     ],
                     'fill' => true,
                     'backgroundColor' => '#288c9830',
                     'borderColor' => '#288c98',
                     'pointBackgroundColor' => '#288c98',
                     'pointBorderColor' => '#fff',
                     'pointHoverBackgroundColor' => '#fff',
                     'pointHoverBorderColor' => '#288c98'
                  ],
                  [
                     'label' => __("Partner proposed"),
                     'data' => [
                        (\App\Models\Department::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNotNull('partner_object_uid')->where('context_type', 'App\\Models\\Department')->whereIn('context_id', \App\Models\Department::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Department::count())),
                        (\App\Models\Process::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNotNull('partner_object_uid')->where('context_type', 'App\\Models\\Process')->whereIn('context_id', \App\Models\Process::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Process::count())),
                        (\App\Models\ProcessActivity::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNotNull('partner_object_uid')->where('context_type', 'App\\Models\\ProcessActivity')->whereIn('context_id', \App\Models\ProcessActivity::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\ProcessActivity::count())),
                        (\App\Models\InformationType::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNotNull('partner_object_uid')->where('context_type', 'App\\Models\\InformationType')->whereIn('context_id', \App\Models\InformationType::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\InformationType::count())),
                        (\App\Models\Asset::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNotNull('partner_object_uid')->where('context_type', 'App\\Models\\Asset')->whereIn('context_id', \App\Models\Asset::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Asset::count())),
                        (\App\Models\Supplier::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNotNull('partner_object_uid')->where('context_type', 'App\\Models\\Supplier')->whereIn('context_id', \App\Models\Supplier::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Supplier::count())),
                        (\App\Models\Customer::count() == 0) ? 0 : round(100*(\App\Models\Risk::whereNull('replacedby_id')->whereNotNull('partner_object_uid')->where('context_type', 'App\\Models\\Customer')->whereIn('context_id', \App\Models\Customer::pluck('id'))->select('context_id')->distinct()->count('context_id')/\App\Models\Customer::count())),
                     ],
                     'fill' => true,
                     'backgroundColor' => '#ffcc0030',
                     'borderColor' => '#ffcc00',
                     'pointBackgroundColor' => '#ffcc00',
                     'pointBorderColor' => '#fff',
                     'pointHoverBackgroundColor' => '#fff',
                     'pointHoverBorderColor' => '#ffcc00'
                  ]
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
