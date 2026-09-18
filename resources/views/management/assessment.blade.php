@php if(!Auth::user()->canAny(['managementtools.edit'])) abort(403); @endphp
@extends('layouts.master')

@section('container')

<script>
var riskmappings = [];
var settingsDirty = false;

$(function(){
   window.addEventListener("beforeunload", beforeUnloadHandler);

   $('#probabilityContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new probability level') }}',
      },
      actions: {
         listAction: '/api/v1/items/ProbabilityLevel',
         createAction: function(postData, jtParams) {
            return {
               'id': -Math.floor(Math.random() * 100000000),
               'name': postData.get('name'),
               'description': postData.get('description'),
            }
         },
         updateAction: function(postData, jtParams) {
            return {
               'name': postData.get('name'),
               'description': postData.get('description'),
            }
         },
         deleteAction: function(postData, jtParams) {
            return [];
         },
         reorderAction: function(data){
            drawRiskMapping();
            return [];
         },
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
            width: '70%',
         },
      },
      rowUpdated: function(event, data){
         settingsDirty = true;
         drawRiskMapping();
      },
      rowInserted: function(event, data){
         if(data.record.preventdelete)
            data.row.find('a.jtable-delete-command-button').remove();

         drawRiskMapping();
      },
      rowsRemoved: function(event, data){
         settingsDirty = true;
         drawRiskMapping();
      },
      recordsLoaded: function(event, data){
         drawRiskMapping();
      }
   });
   $('#probabilityContainer').jtable('load');      
   
   
   $('#consequenceContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new consequence level') }}',
      },
      actions: {
         listAction: '/api/v1/items/ConsequenceLevel',
         createAction: function(postData, jtParams) {
            return {
               'id': -Math.floor(Math.random() * 100000000),
               'name': postData.get('name'),
               'description': postData.get('description'),
            }
         },
         updateAction: function(postData, jtParams) {
            return {
               'name': postData.get('name'),
               'description': postData.get('description'),
            }
         },
         deleteAction: function(postData, jtParams) {
            return [];
         },
         reorderAction: function(data){
            drawRiskMapping();
            return [];
         },
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
           width: '70%',
         },
      },
      rowUpdated: function(event, data){
         settingsDirty = true;
         drawRiskMapping();
      },
      rowInserted: function(event, data){
         if(data.record.preventdelete)
            data.row.find('a.jtable-delete-command-button').remove();

         drawRiskMapping();
      },
      rowsRemoved: function(event, data){
         settingsDirty = true;
         drawRiskMapping();
      },
      recordsLoaded: function(event, data){
         drawRiskMapping();
      }
   });
   $('#consequenceContainer').jtable('load');      
   
   $('#risklevelsContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new risk level') }}',
      },
      actions: {
         listAction: '/api/v1/items/RiskLevel',

         createAction: function(postData, jtParams) {
            return {
               'id': -Math.floor(Math.random() * 100000000),
               'name': postData.get('name'),
               'description': postData.get('description'),
               'color': postData.get('color'),
               'reassessment_days_withoutplans': postData.get('reassessment_days_withoutplans'),
               'reassessment_days_withplans': postData.get('reassessment_days_withplans'),
            }
         },
         updateAction: function(postData, jtParams) {
            return {
               data: {
               'id': postData.get('id'),
               'name': postData.get('name'),
               'description': postData.get('description'),
               'color': postData.get('color'),
               'reassessment_days_withoutplans': postData.get('reassessment_days_withoutplans'),
               'reassessment_days_withplans': postData.get('reassessment_days_withplans'),
            }}
         },
         deleteAction: function(postData, jtParams) {
            return [];
         },
         reorderAction: function(data){
            drawRiskMapping();
            return [];
         },
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
            width: '20%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '40%',
            required: true,
         },
         reassessment_days_withoutplans: {
            title: '{{ __('Reassessment without planned actions (days)') }}',
            type: 'number',
            create: true,
            edit: true,
            list: true,
            tooltip: '{{ __("0 = never") }}',
            required: true,
            step: 1,
            min: 0,
            max: 1000,
            defaultValue: 365,
            width: '20%',
         },
         reassessment_days_withplans: {
            title: '{{ __('Reassessment with planned actions (days)') }}',
            type: 'number',
            create: true,
            edit: true,
            list: true,
            tooltip: '{{ __("0 = never") }}',
            required: true,
            step: 1,
            min: 0,
            max: 1000,
            defaultValue: 180,
            width: '20%',
         },
         color: {
            title: '{{ __('Color') }}',
            create: true,
            edit: true,
            list: false,
            input: function(data) {
               return '<input id="Edit-color" name="color" type="color" value="'+(data.record ? '#'+data.record.color : '#000000')+'" required="required" />';
            }
         },
      },
      rowUpdated: function(event, data){
         $(data.row[0]).find('td:first>span.jtable-cell-content')
            .attr('data-color', (!data.record.color.includes('#') ? '#' : '')+data.record.color)
            .css({ 'border-left': '10px solid '+(!data.record.color.includes('#') ? '#' : '')+data.record.color, 'padding-left': '15px'});

         settingsDirty = true;
         drawRiskMapping();
      },
      rowInserted: function(event, data){
         $(data.row[0]).find('td:first>span.jtable-cell-content')
            .attr('data-color', '#'+data.record.color)
            .css({ 'border-left': '10px solid #'+data.record.color, 'padding-left': '15px' });

         drawRiskMapping();
      },
      rowsRemoved: function(event, data){
         settingsDirty = true;
         drawRiskMapping();
      },
      recordsLoaded: function(event, data){
         drawRiskMapping();
      }
   });
   $('#risklevelsContainer').jtable('load');         
   
   $('#confidentialityContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new confidentiality level') }}',
      },
      actions: {
         listAction: '/api/v1/items/ConfidentialityClass',
         createAction: '/api/v1/items/ConfidentialityClass',
         updateAction: '/api/v1/items/ConfidentialityClass',
         deleteAction: '/api/v1/items/ConfidentialityClass',
         reorderAction: '/api/v1/items/ConfidentialityClass',
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '70%',
            required: true,
         },
      },
   });
   $('#confidentialityContainer').jtable('load');        
   
   $('#integrityContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new integrity level') }}',
      },
      actions: {
         listAction: '/api/v1/items/IntegrityClass',
         createAction: '/api/v1/items/IntegrityClass',
         updateAction: '/api/v1/items/IntegrityClass',
         deleteAction: '/api/v1/items/IntegrityClass',
         reorderAction: '/api/v1/items/IntegrityClass',
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '70%',
            required: true,
         },
      },
   });
   $('#integrityContainer').jtable('load');        
   
   $('#availabilityContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new availability level') }}',
      },
      actions: {
         listAction: '/api/v1/items/AvailabilityClass',
         createAction: '/api/v1/items/AvailabilityClass',
         updateAction: '/api/v1/items/AvailabilityClass',
         deleteAction: '/api/v1/items/AvailabilityClass',
         reorderAction: '/api/v1/items/AvailabilityClass',
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '70%',
            required: true,
         },
      },
   });
   $('#availabilityContainer').jtable('load');        

@if(!config('ledningssystemet.disable_gdpr'))
         
   $('#dataCategoryContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new data category') }}',
      },
      actions: {
         listAction: '/api/v1/items/DataCategory',
         createAction: '/api/v1/items/DataCategory',
         updateAction: '/api/v1/items/DataCategory',
         deleteAction: '/api/v1/items/DataCategory',
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '50%',
            required: true,
         },
         sensitive: {
            title: '{{ __('Sensitive?') }}',
            options: { 0: 'No', 1: 'Yes' },
            create: true,
            edit: true,
            list: true,
            width: '20%',
         },
      },
   });
   $('#dataCategoryContainer').jtable('load');          
   
   $('#subjectCategoryContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new subject category') }}',
      },
      actions: {
         listAction: '/api/v1/items/SubjectCategory',
         createAction: '/api/v1/items/SubjectCategory',
         updateAction: '/api/v1/items/SubjectCategory',
         deleteAction: '/api/v1/items/SubjectCategory',
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '50%',
            required: true,
         },
      },
   });
   $('#subjectCategoryContainer').jtable('load');          
   
   $('#recipientCategoryContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new recipient category') }}',
      },
      actions: {
         listAction: '/api/v1/items/RecipientCategory',
         createAction: '/api/v1/items/RecipientCategory',
         updateAction: '/api/v1/items/RecipientCategory',
         deleteAction: '/api/v1/items/RecipientCategory',
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '50%',
            required: true,
         },
      },
   });
   $('#recipientCategoryContainer').jtable('load');             
   
   $('#legalBasisContainer').jtable({
      messages: {
         addNewRecord: '{{ __('Add new legal basis') }}',
      },
      actions: {
         listAction: '/api/v1/items/LegalBasis',
         createAction: '/api/v1/items/LegalBasis',
         updateAction: '/api/v1/items/LegalBasis',
         deleteAction: '/api/v1/items/LegalBasis',
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
            width: '30%',
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            width: '40%',
            required: true,
         },
         sensitive: {
            title: '{{ __('Allow for sensitive data?') }}',
            options: { 0: 'No', 1: 'Yes' },
            create: true,
            edit: true,
            list: true,
            width: '15%',
         },
         consent: {
            title: '{{ __('Consent based?') }}',
            options: { 0: 'No', 1: 'Yes' },
            create: true,
            edit: true,
            list: true,
            width: '15%',
         },
      },
   });
   $('#legalBasisContainer').jtable('load');          
@endif

   $('#sustainabilityAspectsContainer').jtable({
      title: '{{ __("Sustainability aspects") }}',
      bootstrap: true,
      accordion: false,
      searchfield: true,
      messages: {
         addNewRecord: '{{ __('Add new aspect') }}',
      },
      actions: {
         listAction: '/api/v1/items/SustainabilityAspect',
         createAction: '/api/v1/items/SustainabilityAspect',
         updateAction: '/api/v1/items/SustainabilityAspect',
         deleteAction: '/api/v1/items/SustainabilityAspect',
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
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         threshold: {
            title: '{{ __('Significant threshold') }}',
            type: 'number',
            step: 1,
            create: false,
            edit: true,
            list: true,
            required: true,
         },
         sustainability_metrics: {
            title: '{{ __('Sustainability metrics') }}',
            create: false,
            edit: true,
            list: true,
            multiple: true,
            tooltip: '{{ __("Note: if you have created a new sustainability metric the page must be reloaded before it is availabile for selection here") }}',
            options: [
@foreach(\App\Models\SustainabilityMetric::orderBy('name')->get()->each->setAppends([]) as $obj)
               { Value: {{ $obj->id }}, DisplayText: @php echo(json_encode($obj->name)); @endphp },
@endforeach            
            
            ]
         },
      },
   });
   $('#sustainabilityAspectsContainer').jtable('load');          
    
    $('#sustainabilityMetricsContainer').jtable({
      title: '{{ __("Sustainability metrics") }}',
      bootstrap: true,
      accordion: true,
      searchfield: true,
      messages: {
         addNewRecord: '{{ __('Add new metric') }}',
      },
      actions: {
         listAction: '/api/v1/items/SustainabilityMetric',
         createAction: '/api/v1/items/SustainabilityMetric',
         updateAction: '/api/v1/items/SustainabilityMetric',
         deleteAction: '/api/v1/items/SustainabilityMetric',
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
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         sustainability_metric_levels: {
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
                  .text('{{ __('Levels') }}')
                  .prepend($('<span>stairs_2</span>')
                     .addClass('material-symbols-rounded'));
                  
               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#sustainabilitymetricleveltable').length)
                  {
                     $('#sustainabilityMetricsContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }
                  
                  $('#sustainabilityMetricsContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("Requirements") }}',
                        tableId: 'sustainabilitymetricleveltable',
                        paging: false,
                        actions: {
                           listAction: '/api/v1/items/SustainabilityMetricLevel?sustainability_metric_id='+sourcedata.record.id,
                           updateAction: '/api/v1/items/SustainabilityMetricLevel',
                           createAction: '/api/v1/items/SustainabilityMetricLevel',
                           deleteAction: '/api/v1/items/SustainabilityMetricLevel',
                        },
                        fields: {
                           sustainability_metric_id: {
                              type: 'hidden',
                              create: true,
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: true,
                              maxlength: 255,
                              width: '30%',
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              width: '55%',
                              required: true,
                           },
                           multiplier: {
                              title: '{{ __('Multiplier') }}',
                              type: 'number',
                              step: 1,
                              create: true,
                              edit: true,
                              list: true,
                              width: '15%',
                              required: true,
                              defaultValue: 1,
                           },
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
   $('#sustainabilityMetricsContainer').jtable('load');


   $('#ghgConversionCategoriesContainer').jtable({
      title: '{{ __("GHG Categories") }}',
      bootstrap: true,
      accordion: true,
      searchfield: false,
      messages: {
         addNewRecord: '{{ __('Add new category') }}',
      },
      actions: {
         listAction: '/api/v1/items/GhgCategory',
         createAction: '/api/v1/items/GhgCategory',
         updateAction: '/api/v1/items/GhgCategory',
         deleteAction: '/api/v1/items/GhgCategory',
      },
      fields: {
         id: {
            key: true,
            list: false,
            create: false,
            edit: false,
         },
         partner_id: {
            title: '',
            list: true,
            edit: false,
            create: false,
            display: function (data) {
               if(data.record.partner_id)
               {
                  return $('<span />')
                     .text('{{ __("This is a partner-provided category") }}')
                     .css({'font-style': 'italic', 'color': '#6c757d'});

               }
               return '';
            },
         },
         scope: {
            title: '{{ __('Scope') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            options: {
               1: '{{ __("Scope 1") }}',
               2: '{{ __("Scope 2") }}',
               3: '{{ __("Scope 3") }}',
            }
         },
         name: {
            title: '{{ __('Name') }}',
            create: true,
            edit: true,
            list: true,
            required: true,
            header: true,
            maxlength: 255,
         },
         description: {
            title: '{{ __('Description') }}',
            type: 'textarea',
            create: true,
            edit: true,
            list: true,
            required: true,
         },
         ghg_conversion_factors: {
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
                  .text('{{ __('GHG Conversion factors') }}')
                  .prepend($('<span>calculate</span>')
                     .addClass('material-symbols-rounded'));

               retobj.click(function () {
                  if(retobj.closest('.accordion-body').find('#conversionfactorstable').length)
                  {
                     $('#ghgConversionCategoriesContainer').jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
                     return;
                  }

                  $('#ghgConversionCategoriesContainer').jtable('openChildTable',
                     retobj.closest('.accordion-body').find('.accordion-footer'),
                     {
                        title: '{{__("GHG Conversion factors") }}',
                        tableId: 'conversionfactorstable',
                        paging: false,
                        bootstrap: true,
                        accordion: true,
                        searchfield: false,
                        actions: {
                           listAction: '/api/v1/items/GhgConversionFactor?ghg_category_id='+sourcedata.record.id,
                           updateAction: '/api/v1/items/GhgConversionFactor',
                           createAction: !sourcedata.record.partner_id &&'/api/v1/items/GhgConversionFactor',
                           deleteAction: '/api/v1/items/GhgConversionFactor',
                        },
                        fields: {
                           ghg_category_id: {
                              type: 'hidden',
                              create: true,
                              defaultValue: sourcedata.record.id,
                           },
                           id: {
                              key: true,
                              list: false,
                           },
                           name: {
                              title: '{{ __('Name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              header: true,
                              required: true,
                              maxlength: 255,
                           },
                           partner_id:{
                              title: '{{ __('Partner') }}',
                              create: true,
                              edit: true,
                              list: true,
                           },
                           description: {
                              title: '{{ __('Description') }}',
                              type: 'textarea',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                           },
                           hr0: {
                              title: '',
                              list: true,
                              edit: true,
                              create: false,
                              display: function(){
                                 return $('<hr />')
                              },
                              input: function(){
                                 return $('<hr />')
                              }
                           },
                           activity_sourceunit: {
                              title: '{{ __('Activity based sourcedata unit') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              maxlength: 30,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },
                           activity_factor: {
                              title: '{{ __('Activity based conversion factor') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              default: 1.0,
                              type: 'number',
                              step: 0.001,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },
                           activity_datasource_name: {
                              title: '{{ __('Activity based data source name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },
                           activity_datasource_url: {
                              title: '{{ __('Activity based data source url') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },
                           hr1: {
                              title: '',
                              list: true,
                              edit: true,
                              create: false,
                              display: function(){
                                 return $('<hr />')
                              },
                              input: function(){
                                 return $('<hr />')
                              }
                           },
                           spend_sourceunit: {
                              title: '{{ __('Spend based sourcedata unit') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              maxlength: 30,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },
                           spend_factor: {
                              title: '{{ __('Spend based conversion factor') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              default: 1.0,
                              type: 'number',
                              step: 0.001,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },
                           spend_datasource_name: {
                              title: '{{ __('Spend based data source name') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },
                           spend_datasource_url: {
                              title: '{{ __('Spend based data source url') }}',
                              create: true,
                              edit: true,
                              list: true,
                              required: false,
                              listClass: 'd-inline-block col-12 col-md-6',
                           },                        },
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
   $('#ghgConversionCategoriesContainer').jtable('load');

   @if(!config('ledningssystemet.disable_archival'))

    $('#diariesContainer').jtable({
        messages: {
            addNewRecord: '{{ __('Add new diary') }}',
        },
        actions: {
            listAction: '/api/v1/items/Diary',
            createAction: '/api/v1/items/Diary',
            updateAction: '/api/v1/items/Diary',
            deleteAction: '/api/v1/items/Diary',
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
                width: '30%',
            },
            description: {
                title: '{{ __('Description') }}',
                type: 'textarea',
                create: true,
                edit: true,
                list: true,
                width: '50%',
                required: true,
            },
        },
    });
    $('#diariesContainer').jtable('load');

    $('#confidentialityGroundsContainer').jtable({
        messages: {
            addNewRecord: '{{ __('Add new confidentiality ground') }}',
        },
        actions: {
            listAction: '/api/v1/items/ConfidentialityGround',
            createAction: '/api/v1/items/ConfidentialityGround',
            updateAction: '/api/v1/items/ConfidentialityGround',
            deleteAction: '/api/v1/items/ConfidentialityGround',
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
                width: '30%',
            },
            description: {
                title: '{{ __('Description') }}',
                type: 'textarea',
                create: true,
                edit: true,
                list: true,
                width: '50%',
                required: true,
            },
        },
    });
    $('#confidentialityGroundsContainer').jtable('load');
@endif
});

const beforeUnloadHandler = (event) => {
   if(settingsDirty)
   {
      event.preventDefault();
      event.returnValue = true;
   }
};


var updatePending = false;
function drawRiskMapping(){
   
   // Prevent unnecessary calls
   if(updatePending)
      return;
   
   // Wait for all pending ajax calls to finish before updating
   if($.active > 0)
   {
      window.setTimeout(drawRiskMapping, 1000);
      return;
   }

   updatePending = true;
   
   // Collect data
   var probabilities = [];
   var consequences = [];
   var risklevels = [];
   
   $('div#probabilityContainer tr.jtable-data-row').each(function(){
      probabilities.push({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first>span.jtable-cell-content').text(),
      });
   });
   
   $('div#consequenceContainer tr.jtable-data-row').each(function(){
      consequences.unshift({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first>span.jtable-cell-content').text(),
      });
   });
   
   $('div#risklevelsContainer tr.jtable-data-row').each(function(){
      risklevels.push({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first>span.jtable-cell-content').text(),
         'color': $(this).find('td:first>span.jtable-cell-content').attr('data-color'),
      });
   });
   
   if((0 == probabilities.length) ||
      (0 == consequences.length) ||
      (0 == risklevels.length))
   {
      $('div#riskMapping').empty();
      $('div#riskMapping').append($('<div />')
         .addClass('noriskmatrixdata')
         .text('Probability, consequence and risk levels need to be defined before any risk level mapping can be performed'));
         
      return;
   }
   
   ajaxGet('/api/v1/RiskAssessmentSettings/riskmappings', function(data){
      var divContainer = $('div#riskMapping');
      divContainer.empty();

      // Create table
      var table = $('<table />')
         .addClass('riskmatrix')
         .appendTo(divContainer);
         
      probabilities.forEach((prob) => {
         var row = $('<tr />')
            .appendTo(table);
            
         $('<th />')
            .addClass('rowheader')
            .text(prob.name)
            .appendTo(row);

         consequences.forEach((cons) => {
            
            // Look up current selection
            var selectedRisklevel = null;
            data.forEach((datarow) => {
               if((datarow['probability'] == prob['id']) &&
                  (datarow['consequence'] == cons['id']))
                  selectedRisklevel = datarow['risklevel'];
            });
            
            var col = $('<td />')
               .attr('probability', prob.id)
               .attr('consequence', cons.id)
               .appendTo(row);
               
               
            var selectcontainer = $('<select />')
               .change(function(){
                  var selected = $(this).find('option:selected');
                  $(this).css({'background-color': selected.attr('data-color')});
               })
               .appendTo(col);
            
            if(null == selectedRisklevel)
            {
               selectcontainer.append($('<option />')
                  .attr('value', -1)
                  .attr('data-color', 'initial')
                  .attr('disabled', true)
                  .attr('selected', true)
                  .text("Select..."));
            }
            
            risklevels.forEach((risklevel) => {
               if(risklevel.id == selectedRisklevel)
                  selectcontainer.css({'background-color': risklevel.color+'50'});
               
               selectcontainer.append($('<option />')
                  .attr('value', risklevel.id)
                  .attr('data-color', risklevel.color+'50')
                  .attr('selected', (risklevel.id == selectedRisklevel))
                  .text(risklevel.name));
            });
            
         });
      });
      
      var tablefooter = $('<tr />')
         .addClass('tablefooter')
         .appendTo(table);
      
      tablefooter.append($('<th />'));
      consequences.forEach((cons) => {
         tablefooter.append($('<th />')
            .text(cons.name)
         );
      });         
      updatePending = false;
   });
}

function saveassessmentsettings()
{
   var probabilities = [];
   var consequences = [];
   var risklevels = [];
   var riskmappings = [];
   
   var ordinal = $('div#probabilityContainer tr.jtable-data-row').length;
   $('div#probabilityContainer tr.jtable-data-row').each(function(){
      ordinal--;
      probabilities.unshift({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first>span.jtable-cell-content').text(),
         'description': $(this).find('td:nth-child(2)>span.jtable-cell-content').text(),
         'ordinal': ordinal,
      });
   });
   
   ordinal = $('div#consequenceContainer tr.jtable-data-row').length;
   $('div#consequenceContainer tr.jtable-data-row').each(function(){
      ordinal--;
      consequences.push({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first>span.jtable-cell-content').text(),
         'description': $(this).find('td:nth-child(2)>span.jtable-cell-content').text(),
         'ordinal': ordinal,
      });
   });
   
   ordinal = $('div#risklevelsContainer tr.jtable-data-row').length;
   $('div#risklevelsContainer tr.jtable-data-row').each(function(){
      ordinal--
      risklevels.push({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first span.jtable-cell-content').last().text(),
         'description': $(this).find('td:nth-child(2) span.jtable-cell-content').last().text(),
         'reassessment_days_withoutplans': $(this).find('td[data-jtable-fieldname="reassessment_days_withoutplans"] .jtable-cell-content').last().text(),
         'reassessment_days_withplans': $(this).find('td[data-jtable-fieldname="reassessment_days_withplans"] .jtable-cell-content').last().text(),
         'color': $(this).find('td:first>span.jtable-cell-content').attr('data-color'),
         'ordinal': ordinal,
      });
   });
 
   $('div#riskMapping select').each(function(){
      var rl = $(this).find(":selected").val();
      var parenttd = $(this).closest('td');
      riskmappings.push({
         'probability': parenttd.attr('probability'),
         'consequence': parenttd.attr('consequence'),
         'risklevel': rl,
      });
   });
   
   ajaxPost('/api/v1/RiskAssessmentSettings', { probabilities: probabilities, consequences: consequences, risklevels: risklevels, riskmappings: riskmappings }, function(){
      settingsDirty = false;
      location.reload();
   });
}


function saveclassificationsettings()
{
   var confidentiality = [];
   var availability = [];
   var integrity = [];
   
   var ordinal = $('div#confidentialityContainer tr.jtable-data-row').length;
   $('div#confidentialityContainer tr.jtable-data-row').each(function(){
      ordinal--;
      confidentiality.unshift({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first>span.jtable-cell-content').text(),
         'description': $(this).find('td:nth-child(2)>span.jtable-cell-content').text(),
         'ordinal': ordinal,
      });
   });
   
   ordinal = $('div#availabilityContainer tr.jtable-data-row').length;
   $('div#availabilityContainer tr.jtable-data-row').each(function(){
      ordinal--;
      availability.push({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first>span.jtable-cell-content').text(),
         'description': $(this).find('td:nth-child(2)>span.jtable-cell-content').text(),
         'ordinal': ordinal,
      });
   });
   
   ordinal = $('div#integrityContainer tr.jtable-data-row').length;
   $('div#integrityContainer tr.jtable-data-row').each(function(){
      ordinal--
      integrity.push({
         'id': $(this).attr('data-record-key'),
         'name': $(this).find('td:first span.jtable-cell-content').last().text(),
         'description': $(this).find('td:nth-child(2) span.jtable-cell-content').last().text(),
         'ordinal': ordinal,
      });
   });
 
   
   ajaxPost('/api/v1/RiskAssessmentSettings/informationclassification', { confidentiality: confidentiality, availability: availability, integrity: integrity }, function(){
      location.reload();
   });
}
</script>
<style type="text/css">
   h2 {
      margin-top: 20px;
   }

   table.riskmatrix {
      border: 1px solid #ddd;
      border-collapse: collapse;
   }
   
   table.riskmatrix tr {
      border: 1px solid #ddd;
      border-collapse: collapse;
      padding: 5px;
   }
   
   table.riskmatrix td {
      border-collapse: collapse;
      border: 1px solid #ddd;
      min-height: 1.5em;
      min-width: 20px;
      padding: 5px;
      cursor: pointer;
   }
   
   table.riskmatrix th {
      border-collapse: collapse;
      border: 1px solid #ddd;
      min-height: 1.5em;
      min-width: 20px;
      padding: 5px;
      font-size: 0.8em;
      font-weight: 600;
   }
   
   div.noriskmatrixdata {
      font-style: italic;
   }
   
   table.riskmatrix select {
      -webkit-appearance:none;
      -moz-appearance:none;
      -ms-appearance:none;
      appearance: none;
      padding: 0 28px 0 8px;
      border: 1px solid #ccc;
      outline: 0;
      font-size: 14px;
      border-radius: 0;
      background: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23000%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E') no-repeat;
      background-position: 98% 50%;
      background-size: 8px;    
   }
   
   div#riskassessmenttabcontent .alert {
      margin-top: 20px;
   }
   
   span.note {
      font-weight: 600;
   }
   
</style>

<ul class="nav nav-tabs" id="pageTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="riskassessmenttab" data-bs-toggle="tab" data-bs-target="#riskassessmenttabcontent" type="button" role="tab" aria-controls="riskassessmenttab" aria-selected="true">{{ __("Risk assessment") }}</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="infoclassingtab" data-bs-toggle="tab" data-bs-target="#infoclassingtabcontent" type="button" role="tab" aria-controls="infoclassingtab" aria-selected="true">{{ __("Information classification") }}</button>
  </li>
@if(!config('ledningssystemet.disable_gdpr'))
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="dataprivacytab" data-bs-toggle="tab" data-bs-target="#dataprivacytabcontent" type="button" role="tab" aria-controls="dataprivacytab" aria-selected="true">{{ __("Data privacy") }}</button>
  </li>
@endif  
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="sustainabilitysettingstab" data-bs-toggle="tab" data-bs-target="#sustainabilitysettingstabcontent" type="button" role="tab" aria-controls="sustainabilitysettingstab" aria-selected="true">{{ __("Sustainability settings") }}</button>
  </li>
@if(!config('ledningssystemet.disable_archival'))
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="archivesettingstab" data-bs-toggle="tab" data-bs-target="#archivesettingstabcontent" type="button" role="tab" aria-controls="archivesettingstab" aria-selected="true">{{ __("Archival settings") }}</button>
  </li>
@endif
</ul>
<div class="tab-content" id="tabContents">
  <div class="tab-pane fade show active" id="riskassessmenttabcontent" role="tabpanel" aria-labelledby="riskassessmenttab">
      <div class="alert alert-info"><span class="note">{{ __("Note") }}:</span> {{ __("Any changes made to the tables in this tab needs to be saved before any actual changes take place") }}</div>
      <button class="btn btn-outline-primary btn-sm" style="margin: 20px 0 20px 0;" onClick="saveassessmentsettings();">
         <span class="material-symbols-rounded">save</span>
         {{ __("Save settings") }}
      </button>
      <h2>{{ __("Probability levels") }}</h2>
      <div id="probabilityContainer"></div>

      <h2 style="margin-top: 50px;">{{ __("Consequence levels") }}</h2>
      <div id="consequenceContainer"></div>

      <h2 style="margin-top: 50px;">{{ __("Risk levels") }}</h2>
      <div id="risklevelsContainer"></div>

      <h2 style="margin-top: 50px;">{{ __("Risk level mapping") }}</h2>
      <div id="riskMapping"></div>
  
  </div>
  <div class="tab-pane fade show" id="infoclassingtabcontent" role="tabpanel" aria-labelledby="infoclassingtab">
      <h2>{{__("Confidentiality levels") }}</h2>
      <div id="confidentialityContainer"></div>

      <h2 style="margin-top: 50px;">{{ __("Integrity levels") }}</h2>
      <div id="integrityContainer"></div>

      <h2 style="margin-top: 50px;">{{ __("Availability levels") }}</h2>
      <div id="availabilityContainer"></div>
  </div>
@if(!config('ledningssystemet.disable_gdpr'))
  <div class="tab-pane fade show" id="dataprivacytabcontent" role="tabpanel" style="margin-top: 20px;" aria-labelledby="dataprivacytab">
      <h2>{{ __("Data categories") }}</h2>
      <div id="dataCategoryContainer"></div>

      <h2 style="margin-top: 50px;">{{ __("Subject categories") }}</h2>
      <div id="subjectCategoryContainer"></div>

      <h2 style="margin-top: 50px;">{{ __("Recipient categories") }}</h2>
      <div id="recipientCategoryContainer"></div>
      
      <h2 style="margin-top: 50px;">{{ __("Legal basis") }}</h2>
      <div id="legalBasisContainer"></div>
  </div>
@endif
  <div class="tab-pane fade show" id="sustainabilitysettingstabcontent" role="tabpanel" style="margin-top: 20px;" aria-labelledby="sustainabilitysettingstab">
      <div id="sustainabilityAspectsContainer"></div>
      
      <div style="margin-top: 50px;" id="sustainabilityMetricsContainer"></div>

     <div style="margin-top: 50px;" id="ghgConversionCategoriesContainer"></div>
  </div>
@if(!config('ledningssystemet.disable_archival'))
  <div class="tab-pane fade show" id="archivesettingstabcontent" role="tabpanel" style="margin-top: 20px;" aria-labelledby="archivesettingstab">
      <h2>{{ __("Confidentiality grounds") }}</h2>
      <div id="confidentialityGroundsContainer"></div>

      <h2>{{ __("Diaries") }}</h2>
      <div id="diariesContainer"></div>
  </div>
@endif
</div>
@endsection
