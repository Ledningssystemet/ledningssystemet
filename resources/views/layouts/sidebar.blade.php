<style type="text/css">
   span.badge {
      font-size: 8pt;
   }
</style>
<script>
$(function(){
   $('#sidebar').on('statuschanged', function() {
      
      setCookieValue('sidebar.hidden', $('#sidebar').hasClass('hide'));
      setCookieValue('sidebar.narrow', $('#sidebar').hasClass('sidebar-narrow-unfoldable'));
   });
   
   // Set sidebar status
   var showsidebar = (window.innerWidth && (window.innerWidth > 576));
   var shownarrow = false;
   
   if(!window.innerWidth || (window.innerWidth > 576))
   {
      let cookieVal = getCookieValue('sidebar.hidden');
      if(null !== cookieVal)
         showsidebar = !cookieVal;
      
      cookieVal = getCookieValue('sidebar.narrow');
      if(null !== cookieVal)
         shownarrow = cookieVal;
   }
   
   var removeClasses = '';
   var addClasses = '';
   
   if(showsidebar)
      removeClasses += ('' != removeClasses ? ' ' : '')+'hide';
   else
      addClasses += ('' != addClasses ? ' ' : '')+'hide';
   
   if(shownarrow)
      addClasses += ('' != addClasses ? ' ' : '')+'sidebar-narrow-unfoldable';
   else
      removeClasses += ('' != removeClasses ? ' ' : '')+'sidebar-narrow-unfoldable';
   
      
   $('#sidebar').removeClass(removeClasses).addClass(addClasses);
});
</script>

<div class="sidebar sidebar-fixed border simplebar hide" id="sidebar">
   <ul class="sidebar-nav" data-coreui="navigation">
      <div data-ui-slot="sidebar.top"></div>
      <li class="nav-item" id="nav-home">
         <a class="nav-link" href="/">
            <div class="nav-item-status"></div>
            <span class="nav-icon material-symbols-rounded">home</span>
            {{ __("Home") }}
         </a>
      </li>     
@if(request()->user()->can('dashboard.read'))
      <li class="nav-item" id="nav-dashboard">
         <a class="nav-link" href="/dashboard">
            <div class="nav-item-status"></div>
            <span class="nav-icon material-symbols-rounded">monitoring</span>
            {{ __("Dashboard") }}
         </a>
      </li>
@endif
      <div data-ui-slot="sidebar.groups.top"></div>
      <li class="nav-group">
         <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="collapse" role="button" href="#mycontributions-menu">
            <div class="nav-group-status"></div>
            {{ __("My contributions") }}
         </a>
         <ul class="nav-group-items collapse" id="mycontributions-menu" data-bs-parent="#sidebar">
@if(!config('ledningssystemet.disable_staff'))
            <li class="nav-item" id="nav-me">
               <a class="nav-link" href="/user/me">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">badge</span>
@if(\App\Models\User::where('manager_user_id', auth()->user()->id)->exists())
            {{ __("Me and my employees") }}
@else
            {{ __("My employment") }}
@endif
               </a>
            </li>
@endif
            <li class="nav-item" id="nav-mycontrolaction">
               <a class="nav-link" href="/user/controlactions">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">event_list</span>
                  {{ __("Actions") }}
               </a>
            </li>
            <li class="nav-item" id="nav-myactivity">
               <a class="nav-link" href="/user/activities">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">pending_actions</span>
                  {{ __("Activities") }}
               </a>
            </li>
@if(auth()->user()->can('publishdocument', \App\Models\DocumentVersion::class) || \App\Models\LibraryDocument::where('responsible_user_id', auth()->user()->id)->exists())
            <li class="nav-item" id="nav-mylibrarydocument">
               <a class="nav-link" href="/user/documents">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">docs</span>
                  {{ __("My documents") }}
               </a>
            </li>
   @endif
         </ul>
      </li>
      <li class="nav-group">
         <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="collapse" role="button" href="#inventory-menu">
            <div class="nav-group-status"></div>
            {{ __("Inventory") }}
         </a>
         <ul class="nav-group-items collapse" id="inventory-menu" data-bs-parent="#sidebar">
@if(request()->user()->can('index', App\Models\RequirementSource::class))         
            <li class="nav-item" id="nav-requirementsource">
               <a class="nav-link" href="/inventory/requirements">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">gavel</span>
                  {{ __("Requirement sources") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Process::class))         
            <li class="nav-item" id="nav-process">
               <a class="nav-link" href="/inventory/processes">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">label_important</span>
                  {{ __("Processes") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\InformationType::class))         
            <li class="nav-item" id="nav-informationtype">
               <a class="nav-link" href="/inventory/informationtypes">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">breaking_news</span>
                  {{ __("Information types") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Asset::class))         
            <li class="nav-item" id="nav-asset">
               <a class="nav-link" href="/inventory/assets">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">database</span>
                  {{ __("Assets") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Customer::class))         
            <li class="nav-item" id="nav-customer">
               <a class="nav-link" href="/inventory/customers">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">diamond</span>
                  {{ __("Customers") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Supplier::class))         
            <li class="nav-item" id="nav-supplier">
               <a class="nav-link" href="/inventory/suppliers">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">local_shipping</span>
                  {{ __("Suppliers") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Agreement::class))         
            <li class="nav-item" id="nav-agreement">
               <a class="nav-link" href="/inventory/agreements">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">handshake</span>
                  {{ __("Agreements") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Control::class))         
            <li class="nav-item" id="nav-control">
               <a class="nav-link" href="/inventory/controls">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">encrypted</span>
                  {{ __("Controls") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\ProcessSustainabilityAspect::class))         
            <li class="nav-item" id="nav-processsustainabilityaspect">
               <a class="nav-link" href="/inventory/sustainabilityaspects">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">eco</span>
                  {{ __("Sustainability aspects") }}
               </a>
            </li>
@endif
@if(request()->user()->canAny(['chemicalregister.read', 'chemicalregister.edit']))     
            <li class="nav-item" id="nav-chemical">
               <a class="nav-link" href="/inventory/chemicalregister">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">science</span>
                  {{ __("Chemical register") }}
               </a>
            </li>
@endif
@if(!config('ledningssystemet.disable_archival'))
        @if(request()->user()->canAny(['docmgmtplan.read']))
            <li class="nav-item" id="nav-docmgmtplan">
                <a class="nav-link" href="/inventory/docmgmtplan">
                    <div class="nav-item-status"></div>
                    <span class="nav-icon material-symbols-rounded">inventory_2</span>
                    {{ __("Document management plan") }}
                </a>
            </li>
        @endif
    @endif
@if(!config('ledningssystemet.disable_gdpr'))
@if(request()->user()->canAny(['processingregister.read','processingregister.edit']))
            <li class="nav-item" id="nav-processingregister">
               <a class="nav-link" href="/inventory/dataprocessingregister">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">lists</span>
                  {{ __("Privacy inventory") }}
               </a>
            </li>
@endif            
@endif
         </ul>
      </li>
      <li class="nav-group">
         <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="collapse" role="button" href="#assessment-menu">
            <div class="nav-group-status"></div>
            {{ __("Assess and mitigate") }}
         </a>
         <ul class="nav-group-items collapse" id="assessment-menu" data-bs-parent="#sidebar">
@if(request()->user()->can('index', App\Models\RiskProject::class))         
            <li class="nav-item" id="nav-riskproject">
               <a class="nav-link" href="/assessment/riskprojects">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">order_approve</span>
                  {{ __("Risk projects") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Risk::class))         
            <li class="nav-item" id="nav-risk">
               <a class="nav-link" href="/assessment/riskregister">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">warning</span>
                  {{ __("Risk register") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\ComplianceEvaluation::class))         
            <li class="nav-item" id="nav-complianceevaluation">
               <a class="nav-link" href="/assessment/evaluations">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">approval</span>
                  {{ __("Compliance evaluations") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Finding::class))         
            <li class="nav-item" id="nav-finding">
               <a class="nav-link" href="/assessment/findings">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">report</span>
                  {{ __("Findings") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Incident::class))         
            <li class="nav-item" id="nav-incident">
               <a class="nav-link" href="/assessment/incidents">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">e911_emergency</span>
                  {{ __("Incidents") }}
               </a>
            </li>
@endif            
@if(request()->user()->canAny(['allcontrolactions.read']))
            <li class="nav-item" id="nav-controlaction">
               <a class="nav-link" href="/assessment/controlactions">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">event_list</span>
                  {{ __("Actions") }}
               </a>
            </li>
@endif
@if(config('ledningssystemet.exchangeproxy_url') && request()->user()->can('update', App\Models\Form::class))
            <li class="nav-item" id="nav-forms">
               <a class="nav-link" href="/assessment/forms">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">checklist</span>
                  {{ __("Forms") }}
               </a>
            </li>
@endif
         </ul>
      </li>
      <li class="nav-group">
         <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="collapse" role="button" href="#measureandimprove-menu">
            <div class="nav-group-status"></div>
            {{ __("Measure and improve") }}
         </a>
         <ul class="nav-group-items collapse" id="measureandimprove-menu" data-bs-parent="#sidebar">
@if(request()->user()->can('index', App\Models\Risk::class))         
            <li class="nav-item" id="nav-riskoverview">
               <a class="nav-link" href="/measure/riskoverview">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">search_insights</span>
                  {{ __("Risk overview") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\ProcessPerformanceMetric::class))         
            <li class="nav-item" id="nav-processperformancemetric">
               <a class="nav-link" href="/measure/processperformancemetrics">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">trending_up</span>
                  {{ __("Process metrics") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('index', App\Models\Objective::class))         
            <li class="nav-item" id="nav-objective">
               <a class="nav-link" href="/measure/objectives">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">sports_score</span>
                  {{ __("Objectives") }}
               </a>
            </li>
@endif
@if(request()->user()->can('index', App\Models\GhgFactor::class))
            <li class="nav-item" id="nav-ghgcalculator">
               <a class="nav-link" href="/measure/ghgcalculator">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">footprint</span>
                  {{ __("GHG Calculator") }}
               </a>
            </li>
@endif
               </ul>
      </li>      

      <li class="nav-group">
         <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="collapse" role="button" href="#employee-menu">
            <div class="nav-group-status"></div>
            {{ __("Employee management") }}
         </a>
         <ul class="nav-group-items collapse" id="employee-menu" data-bs-parent="#sidebar">
@if(request()->user()->can('create', App\Models\Employee::class))         
            <li class="nav-item" id="nav-employee">
               <a class="nav-link" href="/staff/employees">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">group</span>
                  {{ __("Employees") }}
               </a>
            </li>
@endif
@if(request()->user()->can('create', App\Models\EmployeeRole::class))         
            <li class="nav-item" id="nav-employeerole">
               <a class="nav-link" href="/staff/roles">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">assignment_ind</span>
                  {{ __("Roles") }}
               </a>
            </li>
@endif
@if(request()->user()->can('create', App\Models\Qualification::class))         
            <li class="nav-item" id="nav-qualification">
               <a class="nav-link" href="/staff/qualifications">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">license</span>
                  {{ __("Qualifications") }}
               </a>
            </li>
@endif
@if(request()->user()->can('create', App\Models\Competence::class))         
            <li class="nav-item" id="nav-competence">
               <a class="nav-link" href="/staff/competence">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">school</span>
                  {{ __("Competences") }}
               </a>
            </li>
@endif            
         </ul>
      </li>      
      <li class="nav-group">
         <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="collapse" role="button" href="#management-menu">
            <div class="nav-group-status"></div>
            {{ __("Coordination") }}
         </a>
         <ul class="nav-group-items collapse" id="management-menu" data-bs-parent="#sidebar">
@if(request()->user()->can('create', App\Models\SupplierCategory::class))         
            <li class="nav-item" id="nav-suppliercategory">
               <a class="nav-link" href="/management/suppliercategories">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">category</span>
                  {{ __("Supplier categories") }}
               </a>
            </li>
@endif
@if(request()->user()->can('create', App\Models\ActivityFlowTemplate::class))         
            <li class="nav-item" id="nav-activityflowtemplate">
               <a class="nav-link" href="/management/activityflowtemplates">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">format_list_numbered</span>
                  {{ __("Activity flow templates") }}
               </a>
            </li>
@endif
@if(request()->user()->can('create', App\Models\RiskProjectType::class))         
            <li class="nav-item" id="nav-riskprojecttype">
               <a class="nav-link" href="/management/riskprojecttypes">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">warning</span>
                  {{ __("Risk project types") }}
               </a>
            </li>
@endif
@if(request()->user()->can('create', App\Models\Activity::class))         
            <li class="nav-item" id="nav-activity">
               <a class="nav-link" href="/management/activities">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">pending_actions</span>
                  {{ __("Activities") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('create', App\Models\LibraryDocument::class))         
            <li class="nav-item" id="nav-librarydocument">
               <a class="nav-link" href="/management/documentlibrary">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">docs</span>
                  {{ __("Document library") }}
               </a>
            </li>
@endif
@if(request()->user()->can('managementtools.edit'))
            <li class="nav-item" id="nav-assessmentsettings">
               <a class="nav-link" href="/management/assessment">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">tune</span>
                  {{ __("Assessment settings") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('create', App\Models\Tag::class))         
            <li class="nav-item" id="nav-tag">
               <a class="nav-link" href="/management/tags">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">sell</span>
                  {{ __("Tag collection") }}
               </a>
            </li>
@endif
@if(config('ledningssystemet.exchangeproxy_url') && request()->user()->can('update', App\Models\FormTemplate::class))
            <li class="nav-item" id="nav-formtemplates">
               <a class="nav-link" href="/management/formtemplates">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">checklist</span>
                  {{ __("Form templates") }}
               </a>
            </li>
@endif
         </ul>
      </li>
      <div data-ui-slot="sidebar.groups.before-systemsettings"></div>
      <li class="nav-group">
         <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="collapse" role="button" href="#systemsettings-menu">
            <div class="nav-group-status"></div>
            {{ __("System settings") }}
         </a>
         <ul class="nav-group-items collapse" id="systemsettings-menu" data-bs-parent="#sidebar">
@if(request()->user()->can('create', App\Models\User::class))         
            <li class="nav-item" id="nav-user">
               <a class="nav-link" href="/systemadmin/users">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">person</span>
                  {{ __("Users") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('create', App\Models\Site::class))         
            <li class="nav-item" id="nav-site">
               <a class="nav-link" href="/systemadmin/sites">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">globe</span>
                  {{ __("Sites") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('create', App\Models\Department::class))         
            <li class="nav-item" id="nav-department">
               <a class="nav-link" href="/systemadmin/departments">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">factory</span>
                  {{ __("Departments") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('create', App\Models\Role::class))         
            <li class="nav-item" id="nav-role">
               <a class="nav-link" href="/systemadmin/roles">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">group</span>
                  {{ __("Roles") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('create', App\Models\AccessGroup::class))         
            <li class="nav-item" id="nav-accessgroup">
               <a class="nav-link" href="/systemadmin/access">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">key</span>
                  {{ __("Access groups") }}
               </a>
            </li>
@endif            
@if(request()->user()->can('create', App\Models\PersonalAccessToken::class))         
            <li class="nav-item" id="nav-apitokens">
               <a class="nav-link" href="/systemadmin/apitokens">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">key</span>
                  {{ __("API tokens") }}
               </a>
            </li>
@endif
@if(request()->user()->can('create', App\Models\CustomProperty::class))
            <li class="nav-item" id="nav-customproperty">
               <a class="nav-link" href="/systemadmin/customproperties">
                  <div class="nav-item-status"></div>
                  <span class="nav-icon material-symbols-rounded">edit_attributes</span>
                  {{ __("Custom properties") }}
               </a>
            </li>
@endif
         </ul>
      </li>
      <div data-ui-slot="sidebar.groups.bottom"></div>
   </ul>
   <button class="sidebar-toggler" type="button" onClick="$('#sidebar').toggleClass('sidebar-narrow-unfoldable').trigger('statuschanged');"></button>
</div>
<script>

$(function(){
   // Hide any navigation groups that does not have any sub-items
   $('.sidebar .nav-group').each(function(){
      if(!$(this).find('ul li').length)
         $(this).hide();
   });
   
   // Set active page
   if(window && window.location && window.location.pathname)
   {
      $('.sidebar .nav-link:not(.nav-group-toggle)').each(function(){
         var urlParts = $(this).attr('href').split('?');
         if(urlParts[0] == window.location.pathname)
         {
            $(this).addClass('active');
            $(this).closest('.nav-group').find('.nav-group-toggle').removeClass('collapsed');
            $(this).closest('.nav-group').find('.nav-group-items').addClass('show');
            return;
         }
         // Special treatment for process editor
         else if((window.location.pathname.includes('/processedit') &&
                 (urlParts[0].includes('processes'))))
         {
            $(this).addClass('active');
            $(this).closest('.nav-group').find('.nav-group-toggle').removeClass('collapsed');
            $(this).closest('.nav-group').find('.nav-group-items').addClass('show');
            return;
         }
      });
   }

   // Load status
   ajaxCall({
      url: '/api/v1/getstatus',
      method: 'GET',
      global: false,
      success: function(data){
         // Tag all
         Object.keys(data).forEach((key) => {
            var x = 40;

            if('danger' == data[key])
               $('li#nav-'+key).find('.nav-item-status').addClass('nav-item-status-danger material-symbols-rounded').text('warning');
            else if('warning' == data[key])
               $('li#nav-'+key).find('.nav-item-status').addClass('nav-item-status-warning material-symbols-rounded').text('warning');
            else if('info' == data[key])
               $('li#nav-'+key).find('.nav-item-status').addClass('nav-item-status-info material-symbols-rounded').text('info');
            else
               $('li#nav-'+key).find('.nav-item-status').addClass('nav-item-status-info material-symbols-rounded').text('check');
         });
         
         // Update groups
         $('.sidebar .nav-group-toggle').each(function(){
            if($(this).closest('.nav-group').find('.nav-item-status-danger').length)
               $(this).find('.nav-group-status').addClass('nav-group-status-danger');
            else if($(this).closest('.nav-group').find('.nav-item-status-warning').length)
               $(this).find('.nav-group-status').addClass('nav-group-status-warning');
            else if($(this).closest('.nav-group').find('.nav-item-status-info').length)
               $(this).find('.nav-group-status').addClass('nav-group-status-info');
         });
      }
   });
});

</script>