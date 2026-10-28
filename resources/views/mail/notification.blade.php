<!DOCTYPE html>
<html lang="en">
   <head>
       <meta charset="UTF-8">
       <title>{{ $title }}</title>
       <style type="text/css">
         body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            background-color: #f8f9fa;
            color: #000;
            width: 100vw;
            font-size: 10pt;
            text-align: center;
            justify-content: center;
            padding-top: 5vh;
            display: flex;
         }
         
         .notification {
             width: auto;
             min-width: 300px;
             max-width: 700px;
             border: 1px solid #888;
             background-color: #eee;
             text-align: center;
         }
         
         .title {
            font-weight: bold;
            font-size: 12pt;
            background-color: #555;
            color: #fff;
            border-bottom: 1px solid #888;
            margin-bottom: 20px;
            padding: 10px;
         }
         
         .from {
            padding: 10px;
         }
         
         .message {
            padding: 10px;
         }
         
         .url {
            padding: 3px 20px 3px 20px;
            background-color: #288c98;
            border: 1px solid #288c98;
            border-radius: 30px;
            color: white;
            font-weight: bold;
            font-size: 12pt;
            text-decoration: none;
            margin-bottom: 20px;
         }
         
       </style>
   </head>
   <body>
      <div class="notification">
         <div class="title">{{ $title }}</div>
@if(null != $sender)         
         <div class="from"><b>{{ __("From") }}:</b> {{ $sender->name }}</div>
@endif      
         <div class="message">{{ $messagecontent }}
@if(null != $url)
         <br/><br/>
         <a class="url" href="{{ $url }}">{{__("Go there!") }}</a>
@endif   
         </div>
      </div>
   </body>
</html>