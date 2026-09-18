<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\RiskLevel;
use App\Models\ProbabilityLevel;
use App\Models\ConsequenceLevel;

class AssessmentSettingsController extends Controller
{
   /**
    * Get risk mappings
    */
   public static function getriskmappings()
   {
      $retval = [];
      foreach(ProbabilityLevel::get() as $prob)
      {
         foreach(ConsequenceLevel::get() as $cons)
         {
            $rl = RiskLevel::getRiskLevel($prob, $cons);
            
            $retval[] = array(
               'probability' => $prob->id,
               'consequence' => $cons->id,
               'risklevel' => (null == $rl) ? null : $rl->id,
            );
         }
      }
      
      // Return success
      return response()->json($retval);
   }
   
   /**
   * Save risk assessment levels.
   */
   public function saverisklevels()
   {
      // Get data
      $this->probabilities = request()->input('probabilities', []);
      $this->consequences = request()->input('consequences', []);
      $this->risklevels = request()->input('risklevels', []);
      $this->riskmappings = request()->input('riskmappings', []);
      
      // Validate that at least some input is present
      if((0 == count($this->probabilities)) ||
         (0 == count($this->consequences)) ||
         (0 == count($this->risklevels)))
         abort(400, __('Probability, consequence and risk levels need to be defined'));
      
      // Validate that all combinations are defined
      foreach($this->probabilities as $prob)
      {
         foreach($this->consequences as $cons)
         {
            $found = false;
            foreach($this->riskmappings as $rm)
            {
               if(($rm['probability'] == $prob['id']) &&
                  ($rm['consequence'] == $cons['id']))
               {
                  $found = true;
                  $foundrl = false;
                  foreach($this->risklevels as $rl)
                  {
                     if($rm['risklevel'] == $rl['id'])
                     {
                        $foundrl = true;
                        break;
                     }
                  }
                  if(!$foundrl)
                     abort(400, __('Could not find risk levels for all combinations of probabilities and consequences'));
               }
            }
         }
      }
      
      // Fetch existing probabilities and consequences
      $existingprobs = ProbabilityLevel::orderBy('ordinal')->get();
      $existingcons = ConsequenceLevel::orderBy('ordinal')->get();
      $existingrisklevels = RiskLevel::orderBy('ordinal')->get();
      
      // Calculate what levels to be removed
      $this->probstodelete = [];
      foreach($existingprobs as $eprob)
      {
         $found = false;
         foreach($this->probabilities as $prob)
         {
            if($prob['id'] == $eprob->id)
            {
               $found = true;
               break;
            }
         }
         if(!$found)
         {
            $this->probstodelete[] = $eprob;
            
            // Check if delete is allowed
            if($eprob->int_risks()->count())
               abort(400, __('Deletion of probability level').' '.$eprob->name.' '.__('is not allowed because it is already in use in the system'));
         }
      }
      
      $this->constodelete = [];
      foreach($existingcons as $econs)
      {
         $found = false;
         foreach($this->consequences as $cons)
         {
            if($cons['id'] == $econs->id)
            {
               $found = true;
               break;
            }
         }
         if(!$found)
         {
            $this->constodelete[] = $econs;
            
            // Check if delete is allowed
            if($econs->int_risks()->count())
               abort(400, __('Deletion of consequence level').' '.$econs->name.' '.__('is not allowed because it is already in use in the system'));
         }
      }
      
      $this->risklevelstodelete = [];
      foreach($existingrisklevels as $erl)
      {
         $found = false;
         foreach($this->risklevels as $rl)
         {
            if($rl['id'] == $erl->id)
            {
               $found = true;
               break;
            }
         }
         if(!$found)
         {
            $this->risklevelstodelete[] = $erl;
         }
      }
      
      
      // Begin transaction. If an exception is called, the changes are to be rolled back
      DB::transaction(function(){
         // Remove 
         foreach($this->risklevelstodelete as $obj)
            $obj->delete();
         foreach($this->probstodelete as $obj)
            $obj->delete();
         foreach($this->constodelete as $obj)
            $obj->delete();
            
         // Create/update objects
         foreach(array_keys($this->probabilities) as $objkey)
         {
            $obj = $this->probabilities[$objkey];
            $dbobj = (0 < intval($obj['id'])) ? ProbabilityLevel::find($obj['id']) : new ProbabilityLevel;
            $dbobj->name = $obj['name'];
            $dbobj->description = $obj['description'] ?: "";
            $dbobj->ordinal = $obj['ordinal'];
            $dbobj->save();
            
            $this->probabilities[$objkey]['dbid'] = $dbobj->id;
         }

         foreach(array_keys($this->consequences) as $objkey)
         {
            $obj = $this->consequences[$objkey];
            $dbobj = (0 < intval($obj['id'])) ? ConsequenceLevel::find($obj['id']) : new ConsequenceLevel;
            $dbobj->name = $obj['name'];
            $dbobj->description = $obj['description'] ?: "";
            $dbobj->ordinal = $obj['ordinal'];
            $dbobj->save();
            
            $this->consequences[$objkey]['dbid'] = $dbobj->id;
         }

         foreach(array_keys($this->risklevels) as $objkey)
         {
            $obj = $this->risklevels[$objkey];
            $dbobj = (0 < intval($obj['id'])) ? RiskLevel::find($obj['id']) : new RiskLevel;
            $dbobj->name = $obj['name'];
            $dbobj->description = $obj['description'] ?: "";
            $dbobj->color = isset($obj['color']) ? str_replace('#', '', $obj['color']) : "000000";
            $dbobj->ordinal = $obj['ordinal'];
            $dbobj->reassessment_days_withplans = $obj['reassessment_days_withplans'];
            $dbobj->reassessment_days_withoutplans = $obj['reassessment_days_withoutplans'];
            $dbobj->save();
            
            $this->risklevels[$objkey]['dbid'] = $dbobj->id;
         }
         
         foreach($this->probabilities as $prob)
         {
            foreach($this->consequences as $cons)
            {
               $probobj = ProbabilityLevel::findOrFail($prob['dbid']);
               $consobj = ConsequenceLevel::findOrFail($cons['dbid']);
               
               $currl = RiskLevel::getRiskLevel($probobj, $consobj);
               
               $found = false;
               foreach($this->riskmappings as $riskmapping)
               {
                  if(($riskmapping['probability'] == $prob['id']) &&
                     ($riskmapping['consequence'] == $cons['id']))
                  {
                     $rl = null;
                     foreach($this->risklevels as $risklevel)
                     {
                        if($risklevel['id'] == $riskmapping['risklevel'])
                        {
                           $rl = RiskLevel::findOrFail($risklevel['dbid']);
                           break;
                        }
                     }
                     
                     if(null == $rl)
                        throw new \Exception('Error: could not derive risk level');
                     
                     // If an existing mapping is no longer valid, remove it
                     if(($currl != null) &&
                        ($currl->id != $rl->id))
                     {
                        DB::table('risk_level_mappings')
                           ->where('probability_level_id', $probobj->id)
                           ->where('consequence_level_id', $consobj->id)
                           ->delete();
                     }
                     
                     // Create new
                     if((null == $currl) ||
                        ($currl->id != $rl->id))
                     {
                        DB::table('risk_level_mappings')->insert([
                           'probability_level_id' => $probobj->id,
                           'consequence_level_id' => $consobj->id,
                           'risk_level_id' => $rl->id
                        ]);
                     }
                     
                     $found = true;
                  }
               }
               if(!$found)
                  throw new \Exception('Error: could not find risk mapping objects');
               
            }
         }
      });
      
      // Return success
      return response()->json([]);
   }
}
