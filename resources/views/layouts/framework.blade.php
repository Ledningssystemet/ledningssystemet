<!DOCTYPE html>
<html lang="{{ config('ledningssystemet.locale', 'en') }}">
   <head>
      <base href="./">
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
      <title>{{ config('ledningssystemet.application_name') }}</title>
      <link rel="icon" type="image/png" href="@php echo(file_exists(resource_path().'/brand/logo/favicon.png') ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/favicon.png')) : '/favicon.png'); @endphp">
      @vite(['resources/js/app.js'])
      @vite(['resources/js/loadstyles.js'])
      
      <!-- Vendors styles-->
      <link rel="stylesheet" href="/vendors/bootstrap/font/bootstrap-icons.css">
      
      <!-- Vendor scripts -->
      <script src="/vendors/simplebar/js/simplebar.min.js"></script>
      <script src="/vendors/jquery/js/jquery.min.js"></script>
      <script src="/vendors/select2/js/select2.min.js"></script>
@if(file_exists(public_path().'/vendors/select2/js/i18n/'.config('ledningssystemet.locale', 'en').'.js'))      
      <script src="/vendors/select2/js/i18n/{{config('ledningssystemet.locale', 'en')}}.js"></script>
@endif         
      
      <script src="/vendors/chart.js/js/chart.min.js"></script>
      <script>
         $(function(){
            // Pre-filter to pass CSRF-token
            $.ajaxPrefilter(function( options ) {
               if('GET' != options.type)
               {
                  if(options.url.includes('?'))
                     options.url += '&';
                  else
                     options.url += '?';
                  
                  options.url += '_token={{ csrf_token() }}';
               }
            });         
         });
      </script>
@if(file_exists(resource_path().'/brand/css/main.css'))      
      <style type="text/css">
@php echo(file_get_contents(resource_path().'/brand/css/main.css')); @endphp
      </style>
@endif      
   </head>
   <body>
   @section('bodycontents')
   
   @show
      <script src="/vendors/bootstrap/js/bootstrap.bundle.min.js"></script>
      @auth
         <script src="/i18n/javascript"></script>
      @endauth
      <div id="global-ajax-spinner">
         <div></div>
      </div>
   </body>
</html>