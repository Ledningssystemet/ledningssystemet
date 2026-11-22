<!DOCTYPE html>
<html lang="{{ config('app.locale', 'en') }}">
   <head>
      <base href="./">
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
      <title>{{ config('ledningssystemet.application_name') }}</title>
      <link rel="icon" type="image/png" href="@php echo(file_exists(resource_path().'/brand/logo/favicon.png') ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/favicon.png')) : '/favicon.png'); @endphp">
   </head>
   <body>
   <div data-ui-slot="reactframework.body-start"></div>
   @section('bodycontents')
   @show
      <div data-ui-slot="reactframework.body-end"></div>
   </body>
</html>