<?php
   $path = resource_path().'/lang/'.config('ledningssystemet.locale', 'en').'.json';
   echo("window.translations = [\r\n");
   if(file_exists($path))
   {
      $data = json_decode(file_get_contents($path), true);
      $i = 0;
      foreach(array_keys($data) as $src)
      {
         echo((($i > 0) ? ",\r\n" : "")."{ in: ".json_encode($src).", out: ".json_encode($data[$src])." }");
         $i++;
      }
   }   
   echo("\r\n];");  
