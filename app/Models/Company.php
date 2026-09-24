<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
   public static function getPrettyName($plural = false){ if($plural) { return __("Companies"); } else { return __("Company"); }}
   
   public static function getName()
   {
      return config('ledningssystemet.company_name');
   }
   
   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      return $retval;
   }
   
}
