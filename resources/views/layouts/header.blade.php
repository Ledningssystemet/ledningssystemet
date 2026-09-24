<header class="header header-sticky mainheader">
   <div class="container-fluid">
      <button class="header-toggler px-md-0 me-md-3" type="button" onclick="$('#sidebar').toggleClass('hide').trigger('statuschanged');">
         <span class="material-symbols-rounded">menu</span>
      </button>
      <a href="/" class="logo headerlogo">
         <img alt="Logo" class="mt-2" src="@php echo(file_exists(resource_path().'/brand/logo/logo_header.png') ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_header.png')) : '/images/logo_'.app()->getLocale().'_white.png'); @endphp">
      </a>
      <ul class="header-nav header-nav-userinfo-container me-4">
         <div data-ui-slot="header.before-report"></div>
         <button class="btn btn-sm btn-outline-light link-white user-report-observation" onClick='userReportObservation(@php echo(json_encode(DB::table('departments')->orderBy('name')->select(['id','name'])->get())); @endphp, @php echo(config('ledningssystemet.disable_finding') ? 'false' : 'true'); @endphp )'>
            <span class="material-symbols-rounded">report</span>
            <span class="button-text">
@if(!config('ledningssystemet.disable_finding'))
   {{ __("Report observation/risk") }}
@else
   {{ __("Report risk") }}
@endif
            </span>
         </button>
         <div data-ui-slot="header.after-report"></div>
         <div class="header-nav-userinfo">
            <span class="header-nav-userinfo-title">{{ __("Logged in as") }}:</span>
            <span class="header-nav-userinfo-user">{{ auth()->user()->name }}</span>
         </div>
         <li class="nav-item dropdown d-flex align-items-center">
            <a class="nav-link nav-group-toggle collapsed" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
               <div>
                  <span class="material-symbols-rounded">account_circle</span>
               </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end pt-0 collapse">
               <a class="dropdown-item" href="/user/preferences">
                  <span class="material-symbols-rounded">tune</span>
                  {{ __("My settings") }}
               </a>
               <div data-ui-slot="header.account-menu"></div>
               <a class="dropdown-item" href="#" onclick="event.preventDefault(); window.auth.logout();">
                  <span class="material-symbols-rounded">logout</span>
                  {{ __("Log out") }}
               </a>
@if(file_exists(base_path('/RELEASE')))
               <span class="dropdown-item info-version">
                  <span class="material-symbols-rounded">info</span>
                  v.@php echo(substr(file_get_contents(base_path('/RELEASE')), 0, 7));@endphp
               </span>
@endif
               <div data-ui-slot="header.account-menu-end"></div>
            </div>
         </li>
      </ul>
   </div>
   <div class="subheader">
      <div data-ui-slot="header.subheader"></div>
   </div>
</header>
