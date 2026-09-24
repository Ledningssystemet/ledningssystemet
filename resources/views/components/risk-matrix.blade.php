<?php
   $consequencelevels = \App\Models\ConsequenceLevel::orderBy('ordinal')->select(['id', 'name', 'ordinal'])->get()->each->setAppends([]);
   $probabilitylevels = \App\Models\ProbabilityLevel::orderBy('ordinal', 'desc')->select(['id', 'name', 'ordinal'])->get()->each->setAppends([]);;
   $colcount = (0 < count($consequencelevels)) ? count($consequencelevels)+($attributes->has('hideheaders') ? 0 : 1) : 1;

   $query = null;
   if(!$attributes->has('template')) {
      $query = function (Illuminate\Database\Eloquent\Builder $query) use ($attributes) {
         $query->when($attributes->has('department_id') && (0 < intval($attributes->get('department_id'))), function ($q) use ($attributes) {
            $q->where('department_id', intval($attributes->get('department_id')));
         });

         $query->when($attributes->has('riskowner_id') && (0 < intval($attributes->get('riskowner_id'))), function ($q) use ($attributes) {
            $q->where('riskowner_id', intval($attributes->get('riskowner_id')));
         });

         $query->when(function ($q) use ($attributes) {
            if ($attributes->has('risk_project_id') && (0 < intval($attributes->get('risk_project_id')))) {
               $rp = \App\Models\RiskProject::find(intval($attributes->get('risk_project_id')));
               if (!auth()->user()->can('view', $rp))
                  abort(403);

               $q->where('risk_project_id', intval($attributes->get('risk_project_id')));
            } else
               $q->whereNull('risk_project_id');
         });

         $query
            ->whereNull('replacedby_id')
            ->whereNotNull('assessed_at');
         return $query;
      };
   }

   // Calculate risk count
   $riskcount = [];
   foreach($probabilitylevels as $prob)
   {
      $riskcount[$prob->id] = [];
      foreach($consequencelevels as $cons)
      {
         $count = $attributes->has('template') ? 0 : \App\Models\Risk::when($query)->where('probability_id', $prob->id)->where('consequence_id', $cons->id)->count();
         $riskcount[$prob->id][$cons->id] = $count;
      }
   }
?>
@if((0 < count($consequencelevels)) && (0 < count($probabilitylevels)))
<div class="riskmatrix-container">
@if($attributes->has('title'))
      <h2 class="riskmatrix-header">{{ $attributes->get('title') }}</h2>
@endif
@if(!$attributes->has('hideheaders'))
   <div class="d-flex justify-center">
      <span class="riskmatrix-consequence-label">{{ __("Consequence") }} →</span>
   </div>
@endif
   <div class="d-flex">
@if(!$attributes->has('hideheaders'))
      <div class="d-flex justify-center">
         <span class="riskmatrix-probability-label">{{ __("Probability") }} →</span>
      </div>
@endif
      <div class="riskmatrix-table">
         <div class="riskmatrix-row riskmatrix-title-row" style="grid-template-columns: repeat({{ $colcount }}, minmax(0, 1fr)); ">
@if(!$attributes->has('hideheaders'))
            <div class="riskmatrix-header-col">
               <span>&nbsp;</span>
            </div>
         @foreach($consequencelevels as $cons)
            <div class="riskmatrix-header-col">
               <span>{{ $cons->name }}</span>
            </div>
         @endforeach
@endif
         </div>
      @foreach($probabilitylevels as $prob)
         <div class="riskmatrix-row" style="grid-template-columns: repeat({{ $colcount }}, minmax(0, 1fr)); ">
@if(!$attributes->has('hideheaders'))
            <div class="riskmatrix-row-title">
               <span>{{ $prob->name }}</span>
            </div>
@endif
<?php
   $color = 'ffffff';
   foreach($consequencelevels as $cons)
   {
      $risklevel = \App\Models\RiskLevel::getRiskLevel($prob, $cons);
      $color = $risklevel->color;
      echo("<div style=\"background-color: #".$color."bb\" class=\"riskmatrix-col-item".($attributes->has('nolinks') ? "" : " has-links")."\"".($attributes->has('nolinks') ? '' : " onClick=\"document.location='/assessment/riskregister?jtFilter[riskregister][probability_id]=".$prob->id."&jtFilter[riskregister][consequence_id]=".$cons->id."&jtFilter[riskregister][department_id]=-1&jtFilter[riskregister][showapproved]=1".($attributes->has('riskowner_id') ? "&jtFilter[riskregister][riskowner_id]=".$attributes->get('riskowner_id') : "")."';\"")."><span data-probability-id=\"".$prob->id."\" data-consequence-id=\"".$cons->id."\">".$riskcount[$prob->id][$cons->id]."</span></div>\r\n");
   }
   ?>
         </div>
      @endforeach
      </div>
   </div>
</div>
@endif