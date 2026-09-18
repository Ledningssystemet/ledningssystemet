<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Me extends Model
{
   public static function getPrettyName($plural = false){ if($plural) { return __("Me"); } else { return __("Me"); }}
   
   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if(config('ledningssystemet.disable_staff'))
         return [];
      
      $retval = [];

      if((null == $user) || (null != $department))
         return [];
      
      $employee = Employee::createFromUser($user);
      $missing = false;
      $expired = false;
      $soonexpired = false;
      
      foreach($employee->qualifications as $role)
      {
         foreach($role['qualifications'] as $qualification)
         {
            if($qualification['mandatory']&& !$qualification['finished_at'])
               $missing = true;
            
            if($qualification['mandatory'] && $qualification['expires_at'] && (strtotime($qualification['expires_at']) < time()))
               $expired = true;
            
            if($qualification['mandatory'] && $qualification['expires_at'] && (strtotime($qualification['expires_at']) < strtotime("+1 MONTHS")))
               $soonexpired = true;
         }
      }
      $compnotevaluated = false;
      $compnotsufficient = false;
      
      foreach($employee->int_competences() as $comprole)
      {
         foreach($comprole['competences'] as $comp)
         {
            if(null == $comp['achieved_level'])
               $compnotevaluated = true;
            else if(CompetenceLevel::findOrFail($comp['achieved_level']->competence_level_id)->ordinal > $comp['acceptable_level']->ordinal)
               $compnotsufficient = true;
         }
      }      
      
      if($missing)
         $retval[] = ['level' => 'danger', 'count' => 1, 'area' => 'qualification', 'text' => ("Missing mandatory qualifications"), 'url' => ''];

      if($expired)
         $retval[] = ['level' => 'danger', 'count' => 1, 'area' => 'qualification', 'text' => ("Have expired, mandatory, qualifications"), 'url' => ''];
      
      if($soonexpired)
         $retval[] = ['level' => 'warning', 'count' => 1, 'area' => 'qualification', 'text' => ("Have mandatory qualifications that soon expires"), 'url' => ''];

      if($compnotevaluated)
         $retval[] = ['level' => 'danger', 'count' => 1, 'area' => 'competence', 'text' => ("Missing competence evaluation"), 'url' => ''];
      
      if($compnotsufficient)
         $retval[] = ['level' => 'warning', 'count' => 1, 'area' => 'competence', 'text' => ("Insufficient competence"), 'url' => ''];
      
      return $retval;
   }      
}