<!DOCTYPE html>
<html lang="en">
   <head>
       <meta charset="UTF-8">
       <title>{{ config('ledningssystemet.application_name') }} {{ __("status overview for") }} {{ $user->name }}</title>
       <style type="text/css">
         body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            background-color: #f8f9fa;
            color: #000;
         }
       
         span.introtext {
            
         }
         
         div.status-item {
            position: relative;
            display: block;
            margin-top: 10px;
            text-decoration: none;
            font-size: 12pt;
            color: black;
         }
         
         div.status-item a {
            text-decoration: underline dotted;
            color: #288c98;
         }
         
         div.status-header {
            background-color: #3a3a3a;
            color: white;
            font-size: 14pt;
            padding: 10px;
            margin-top: 20px;
         }
         
         .count {
            width: 30px;
            height: 30px;
            font-size: 15px;
            display: inline-block;
            text-align: center;
            padding: 10px 3px 0px 3px;
            font-weight: 600;
            text-overflow: ellipsis;
         }
         
         .center {
            text-align: center;
            width: 100px;
         }
         
         .bg-info {
            background-color: #779a9e;
            color: white;
         }
         
         .bg-success {
            background-color: #198754;
            color: white;
         }
         
         .bg-warning {
            background-color: #ffcc00;
            color: white;
         }
         
         .bg-danger {
            background-color: #e45316;
            color: white;
         }
       </style>
   </head>
   <body>
      <span class="introtext">
      {{ __("Hi") }}!
         <br /><br />
         {{ __("As a contributing employee of") }} <a href="{{ config('ledningssystemet.application_url') }}" target="_blank">{{ config('ledningssystemet.application_name') }}</a> {{ __("for") }} {{ config('ledningssystemet.company_name') }} {{ __("you receive a status overview according to your own preferences (configurable in the system)") }}.
      </span>
@foreach($sections as $header => $issues)
      <div>
         <div class="status-header">{{ __($header) }}</div>
@foreach($issues as $issue)
         <div class="container status-item">
            <span class="count bg-{{ $issue['level'] }}">{{ $issue['count'] }}</span>
@if($issue['url'])
            <a class="status-item-link" href="{{ $issue['url'] }}">{{ $issue['text'] }}</a>
@else
            <span class="status-item-text">{{ $issue['text'] }}</span>
@endif
         </div>
@endforeach
      </div>
@endforeach

@if(0 < count($dueItems))
      <div>
         <div class="status-header">{{ __('Overdue or near due activities and control actions') }}</div>
@foreach($dueItems as $item)
         <div class="container status-item">
            <span class="count bg-{{ $item['level'] }}">&nbsp;</span>
            <span class="status-item-text">{{ $item['text'] }}</span>
         </div>
@endforeach
      </div>
@endif
   </body>
</html>