<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StatusController extends Controller
{
   /**
    * Get status overview. Used for notifications.
    */
   public static function getStatus()
   {
      return Cache::rememberForever('status.user.'.auth()->user()->id, function() {
         $retval = [];

         foreach (scandir(__DIR__ . '/../../Models') as $filename) {
            $myobjstatus = null;
            $objstatus = null;
            if (false === strpos($filename, '.php'))
               continue;

            $modelname = substr($filename, 0, strlen($filename) - 4);
            $classname = 'App\\Models\\' . $modelname;

            // Ensure method exists
            if (!method_exists($classname, 'getItemsStatus'))
               continue;

            // Ensure authorized
            if (auth()->user()->cannot('index', $classname))
               continue;

            // Get status
            foreach ($classname::getItemsStatus(null, auth()->user()) as $status) {
               if (array_key_exists('personal', $status) && $status['personal']) {
                  if (null == $myobjstatus)
                     $myobjstatus = 'info';

                  if (
                     (('info' == $myobjstatus) && ('warning' == $status['level'])) ||
                     (('info' == $myobjstatus) && ('danger' == $status['level'])) ||
                     (('warning' == $myobjstatus) && ('danger' == $status['level'])))
                     $myobjstatus = $status['level'];

               }

               if ((null == $objstatus) ||
                  (('info' == $objstatus) && ('warning' == $status['level'])) ||
                  (('info' == $objstatus) && ('danger' == $status['level'])) ||
                  (('warning' == $objstatus) && ('danger' == $status['level'])))
                  $objstatus = $status['level'];
            }

            $retval[strtolower($modelname)] = $objstatus;
            if (null != $myobjstatus)
               $retval['my' . strtolower($modelname)] = $myobjstatus;
         }

         return $retval;
      });
   }
}

