<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
   /**
    * Get consequencedescription
    */
   public static function getRiskAnalysis()
   {
      $retobj = null;

      $risk = \App\Models\Risk::findOrFail(request()->input('risk_id', 0));
      if(!auth()->user()->can('view', $risk))
         abort(403);


      // Get scenario description and context
      $scenariodescription = $risk->scenariodescription_pretty;
      $consequencedescription = $risk->consequencedescription_pretty;
      $context = $risk->int_context_object();
      
      // Validate scenario description
      if("" == $scenariodescription)
         abort(400, __("A scenario description is necessary for the AI agent to have something to work with"));

      // Prepare prompt
      $prompt = "";

      if(null != $context)
      {
         $prompt .= "The subject for risk assessment is the ".$context::getPrettyName(false)." ".$context->name.".\n";
         
         // Get flags
         $flags = DB::table('object_properties')
            ->where('object_properties.object_properties_type', $context::class)
            ->where('object_properties.object_properties_id', $context->id)
            ->where('object_properties.value', 1)
            ->leftJoin('properties', 'properties.id', '=', 'object_properties.property_id')
            ->get();
         
         if( 0 < count($flags))
         {
            $prompt.= "For your better understanding, object has been tagged with the following properties for risk assessment purposes: ";
            $i = 0;
            
            foreach($flags as $flag)
            {
               $prompt.= ($i++ > 0) ? "," : "";
               
               $prompt.= $flag->name.($flag->description ? "(".$flag->description.")" : "");
            }
            $prompt.=".";
         }
      }
      else
      {
         $prompt .= "The subject for risk assessment is not specified, so try to understand based on the scenariodescription alone, given our company context.";
      }

      $prompt .= "\nA consequence description has already been produced, so please propose modifications to that instead of inventing a new. This is the current description: ".$consequencedescription.".";
      $prompt.= "\nThe risk scenario: ".$scenariodescription;

      $controlnames = [];
      $appliedcontrols = [];
      foreach($risk->int_controls()->whereNull('controls.not_applicable_at')->get() as $control)
      {
         $appliedcontrols[$control->id] = $control;
         $controlnames[] = $control->name;
      }

      if(0 < count($controlnames))
         $prompt .= "\nThe controls that are applicable and associated with this risk are called: ".implode(', ', $controlnames).".\n";

      if(0 < count($appliedcontrols))
      {
         $prompt .= "The referenced controls are here explained for your analysis.";
         foreach($appliedcontrols as $control)
         {
            if($control->statusdescription)
               $prompt .= "\n\"".$control->name."\" has implementation status: \"".$control->description."\".\n";
            else
               $prompt .= "\n\"".$control->name."\" has not been implemented yet.\n";
         }
      }

      $data = array(
         array(
            "role" => "system",
            "content" => "You are a risk assessment expert working at our company ".config('ledningssystemet.company_name', "").", and will provide me with a compact response with potential, yet realistic, consequences and also suggestions on how to mitigate the risk for the risk scope and scenario provided by the user. The response shall be in language ".config('ledningssystemet.locale')." according to ISO 639. You may not respond with questions. If you cannot process the request, please tell why instead.",

         ),
         array(
            "role" => "user",
            "content" => $prompt,
         )
      );


      $airesponse = AIController::getAIResponse($data, __FUNCTION__);
      if(null == $airesponse)
         abort(400, __("Invalid response received from AI"));
      
      return response()->json(array('retval' => $airesponse['data']));
   }

   public static function getRequirementSourceAnalysis()
   {
      $retobj = null;

      $requirementSource = \App\Models\RequirementSource::findOrFail(request()->input('requirement_source_id', 0));
      if(!auth()->user()->can('view', $requirementSource))
         abort(403);

      // Prepare prompt
      $prompt = "";

      // Set context
      $prompt .= "The subject for analysis is the ".$requirementSource->reference.' '.$requirementSource->name.".\n";

      // Provide our description
      $prompt .= "Our description of the general scope and applicability of the requirements within this area is: ".$requirementSource->description.".\n";

      // Provide our analysis of the individual requirements
      $appliedcontrols = [];

      foreach($requirementSource->int_requirements as $req)
      {
         $prompt .= "The analysis of the requirement ".$req->reference.' '.$req->name." in the subject for analysis is: \"".$req->description.'".\n';
         $prompt .= "Our choosen approach to comply with this requirement is:\"".$req->governance.'".\n';
         $controlnames = [];
         foreach($req->int_controls()->whereNull('controls.not_applicable_at')->get() as $control)
         {
            $appliedcontrols[$control->id] = $control;
            $controlnames[] = $control->name;
         }

         if(0 < count($controlnames))
            $prompt .= "The controls that are applicable and selected for implementation of the requirement are called: ".implode(', ', $controlnames).".\n";
      }

      if(0 < count($appliedcontrols))
      {
         $prompt .= "The referenced controls that are applicable to the requirements are here explained for your analysis.";
         foreach($appliedcontrols as $control)
         {
            if($control->statusdescription)
               $prompt .= "\n\"".$control->name."\" has implementation status: \"".$control->description."\".\n";
            else
               $prompt .= "\n\"".$control->name."\" has not been implemented yet.\n";
         }
      }

      $data = array(
         array(
            "role" => "system",
            "content" => "You are a legal expert working at our company ".config('ledningssystemet.company_name', "").", "
                        ."and will provide me with a compact response with your opinion on our analysis on how the requirements are applicable to us and also our chosen approach to comply. The response shall be in language "
                        .config('ledningssystemet.locale')
                        ." according to ISO 639. You may not respond with questions. If you cannot process the request, please tell why instead. If you disagree with our analysis, please explain why."
                        ."Please be critical and use only the provided user information and knowledge about the subject for analysis as a basis for your opinion."
                        ."Please suggest any missing requirements that we need to add in order to have a complete picture of the requirements and their applicability to us.",

         ),
         array(
            "role" => "user",
            "content" => $prompt,
         )
      );


      $airesponse = AIController::getAIResponse($data, __FUNCTION__);
      if(null == $airesponse)
         abort(400, __("Invalid response received from AI"));

      return response()->json(array('retval' => $airesponse['data']));
   }

   public static function getFindingAnalysis(){
      $retobj = null;

      $finding = \App\Models\Finding::findOrFail(request()->input('finding_id', 0));
      if(!auth()->user()->can('view', $finding))
         abort(403);

      // Prepare prompt
      $prompt = "";

      // Set context
      $prompt .= "The subject for analysis is the ".($finding->nonconformity ? "non-conformity":"suggestion for improvement")." named ".$finding->name;

      // Provide our description
      if(!$finding->description)
         abort(400, __("The finding does not have a description, which makes it impossible to analyze it. Please add a description to the finding and try again."));

      $prompt .= "The description of the observation is: \"".$finding->description."\"";

      if($finding->consequence)
         $prompt .= "The description of consequences is: \"".$finding->consequence."\"";

      if($finding->nonconformity)
      {
         if($finding->rootcause)
            $prompt .= "The current analysis of the root-cause (5 why) is: \"".$finding->rootcause."\"";
         else
            $prompt .= "Currently, no root cause analysis has been performed.";

         if($finding->immediateaction)
            $prompt .= "The current proposed immediate corrective actions are: \"".$finding->immediateaction."\"";
         else
            $prompt .= "Currently, no immediate corrective actions have been proposed.";

         if($finding->preventativeaction)
            $prompt .= "The current proposed long-term preventative actions are: \"".$finding->preventativeaction."\"";
         else
            $prompt .= "Currently, no long-term preventative actions have been proposed.";
      }

      $data = array(
         array(
            "role" => "system",
            "content" => $finding->nonconformity ?
               "You are part of our quality department at our company ".config('ledningssystemet.company_name', "").", and will provide me with a compact response with your opinion on how to handle a non-conformity. The response shall be in language ".config('ledningssystemet.locale')." according to ISO 639. You may not respond with questions. If you cannot process the request, please tell why instead. We strive for a good-enough ambition level with cost awareness rather than best-in-class. We need to have a consequence description, a root-cause analysis (5 why), corrective immediate actions and long-term preventative actions."
               :
               "You are part of our quality department at our company ".config('ledningssystemet.company_name', "").", and will provide me with a compact response with your opinion on how to handle a suggestion for improvement. The response shall be in language ".config('ledningssystemet.locale')." according to ISO 639. You may not respond with questions. If you cannot process the request, please tell why instead. We strive for a good-enough ambition level with cost awareness rather than best-in-class. We need an understanding of potential consequences suggestions on a reasonable approach for handling this observation."
            ,

         ),
         array(
            "role" => "user",
            "content" => $prompt,
         )
      );


      $airesponse = AIController::getAIResponse($data, __FUNCTION__);
      if(null == $airesponse)
         abort(400, __("Invalid response received from AI"));

      return response()->json(array('retval' => $airesponse['data']));

   }

   public static function getControlAnalysis(){
      $retobj = null;

      $control = \App\Models\Control::findOrFail(request()->input('control_id', 0));
      if(!auth()->user()->can('view', $control))
         abort(403);

      // Prepare prompt
      $prompt = "";

      // Set context
      $prompt .= "The subject for analysis is the control named ".$control->name.".";

      // Provide our description
      if(!$control->description)
         abort(400, __("The control does not have a description, which makes it impossible to analyze it. Please add a description to the control and try again."));

      $prompt .= "The description of the purpose of the control is: \"".$control->description."\"";

      if($control->statusdescription)
         $prompt .= "The current implementation status of the control is: \"".$control->statusdescription."\"";
      else
         $prompt .= "The control has not been implemented yet.";

      $requirements = $control->int_requirements()->where('requirements.applicable', 1)->get();
      if(0 == count($requirements))
         $prompt .= "The control does not have any requirements that is expects to fulfill.";
      else
      {
         $prompt .= "The requirements that are associated with this control are: ";
         foreach($requirements as $req) {
            $prompt .= "\"" . $req->reference . ' ' . $req->name . "\" with the description \"" . $req->description . '".';
         }
      }

      $risks = $control->int_risks()->get();
      if(0 == count($risks))
         $prompt .= "The control does not have any associated risks.";
      else
      {
         $prompt .= "The risks that are associated with this control are: ";
         foreach($risks as $risk) {
            $prompt .= "\"" . $risk->name_pretty . "\" with the risk scenario \"" . $risk->scenariodescription_pretty . '". The consequence description is: "' . $risk->consequencedescription_pretty . '".';
         }
      }

      $data = array(
         array(
            "role" => "system",
            "content" =>"You are part of our department for quality, security, occupational health and safety and environment at our company ".config('ledningssystemet.company_name', "").", and will provide me with a compact response with your opinion on how our choosen implementation of this control is sufficient for its purpose of adequately handling associated risks and requirements. The response shall be in language ".config('ledningssystemet.locale')." according to ISO 639. You may not respond with questions. If you cannot process the request, please tell why instead. We strive for a good-enough ambition level with cost awareness rather than best-in-class. Be critical and use only the provided user information and knowledge about the subject for analysis as a basis for your opinion.",
         ),
         array(
            "role" => "user",
            "content" => $prompt,
         )
      );


      $airesponse = AIController::getAIResponse($data, __FUNCTION__);
      if(null == $airesponse)
         abort(400, __("Invalid response received from AI"));

      return response()->json(array('retval' => $airesponse['data']));

   }

   public static function getDocument(){
      $retobj = null;

      // Prepare prompt
      $prompt = request()->input('instructions', 'No instructions provided. Please provide instructions for the creation of the document as a document.');
      $document = json_encode(request()->has('document') ? request()->input('document') : []);

      $data = array(
         array(
            "role" => "system",
            "content" =>"You are a writing assistant for us at ".config('ledningssystemet.company_name', "").", and will help create documents in JSON format used by editorjs.org. Return the document in the same language as the user provided instructions. Return only valid JSON, with no markdown, no code fences and no extra explanation. Use only supported block types: header, paragraph and list.",
         ),
         array(
            "role" => "assistant",
            "content" => "Please provide the current document that the user is trying to modify. It shall be in the appropriate JSON format.",
         ),
         array(
            "role" => "user",
            "content" => $document,
         ),
         array(
            "role" => "assistant",
            "content" => "Please provide the instructions on what to do with the document.",
         ),
         array(
            "role" => "user",
            "content" => $prompt,
         )
      );

      // Use generic JSON object formatting from the model; strict Editor.js validation is done server-side below.
      $responseformat = [
         "type" => "json_object",
      ];

      $airesponse = AIController::getAIResponse($data, __FUNCTION__, $responseformat);
      if(null == $airesponse)
         abort(400, __("Invalid response received from AI"));

      $doc = json_decode($airesponse['data'], true);
      if(JSON_ERROR_NONE !== json_last_error())
         abort(400, __("Unfortunately the AI did not return valid JSON: :error", ['error' => json_last_error_msg()]));

      $validationError = self::validateEditorDocument($doc);
      if(null !== $validationError)
         abort(400, $validationError);

      return response()->json(array('retval' => $doc));
   }

   private static function validateEditorDocument($doc)
   {
      if(!is_array($doc))
         return __("Unfortunately the AI did not return a valid document object.");

      if(!array_key_exists('blocks', $doc) || !is_array($doc['blocks']))
         return __("The document must contain a blocks array.");

      $allowedBlockTypes = ['header', 'paragraph', 'list'];
      foreach($doc['blocks'] as $idx => $block)
      {
         if(!is_array($block))
            return __("Block :idx is not a valid object.", ['idx' => $idx]);

         if(!array_key_exists('type', $block) || !is_string($block['type']))
            return __("Block :idx is missing a valid type.", ['idx' => $idx]);

         if(!in_array($block['type'], $allowedBlockTypes))
            return __("Block :idx has unsupported type ':type'.", ['idx' => $idx, 'type' => $block['type']]);

         if(!array_key_exists('data', $block) || !is_array($block['data']))
            return __("Block :idx is missing valid data.", ['idx' => $idx]);

         $err = self::validateEditorBlockData($block['type'], $block['data'], $idx);
         if(null !== $err)
            return $err;
      }

      return null;
   }

   private static function validateEditorBlockData($type, $data, $idx)
   {
      if('header' === $type)
      {
         if(!array_key_exists('text', $data) || !is_string($data['text']))
            return __("Header block :idx must contain text.", ['idx' => $idx]);

         if(array_key_exists('level', $data) && (!is_int($data['level']) || $data['level'] < 1 || $data['level'] > 6))
            return __("Header block :idx has an invalid level.", ['idx' => $idx]);

         return null;
      }

      if('paragraph' === $type)
      {
         if(!array_key_exists('text', $data) || !is_string($data['text']))
            return __("Paragraph block :idx must contain text.", ['idx' => $idx]);

         return null;
      }

      if('list' === $type)
      {
         if(!array_key_exists('style', $data) || !is_string($data['style']) || !in_array($data['style'], ['unordered', 'ordered']))
            return __("List block :idx must contain a supported style.", ['idx' => $idx]);

         if(!array_key_exists('items', $data) || !is_array($data['items']))
            return __("List block :idx must contain an items array.", ['idx' => $idx]);

         foreach($data['items'] as $itemIndex => $item)
         {
            if(!is_string($item))
               return __("List block :idx contains an invalid item at position :item.", ['idx' => $idx, 'item' => $itemIndex]);
         }

         return null;
      }

      return __("Block :idx has unsupported type ':type'.", ['idx' => $idx, 'type' => $type]);
   }

   public static function getRiskSuggestions($contextobj) {
      $systemprompt = <<<EOF
     Generera förslag på risker relaterade till det analysobjekt som beskrivs av användaren. Undvik att sammanfalla med redan identifierade risker, men var inte rädd för att ta med även mer detaljerade och specifika riskaspekter som kan bidra till en bredare och djupare riskanalys.
Generera minst 10 unika, tydligt definierade risker relaterade till arbetsmiljö, informationssäkerhet, kvalitet och eventuellt miljö i samband med scenariot. Fokusera på kreativitet och bredd för att ge en omfattande riskbild.
      Använd informationen och beskrivningar som tillhandahålls för att kreativt och logiskt identifiera relevanta risker, och strukturera resultatet enligt det förutbestämda formatet.

# Steg

1. Läs och analysera den providede beskrivningen av scenariot för att identifiera potentiella risker.
2. Granska listan över redan identifierade risker och uteslut dessa från dina nya förslag.
3. Strukturera varje förslag för en identifierad risk enligt formatet:
- name
- scenariodescription
- consequencedescription

4. Försäkra att riskerna är tydligt och unikt definierade.

# Output Format

Generera resultaten som en strukturerad JSON med följande format:
```json
[
{
"name": "Kort titel för risken",
"scenariodescription": "En tydlig och kortfattad beskrivning av scenariot som kan leda till denna risk.",
"consequencedescription": "En beskrivning av vilka konsekvenser risken kan medföra om den inträffar."
},
{
"name": "Kort titel för risken",
"scenariodescription": "En tydlig och kortfattad beskrivning av scenariot som kan leda till denna risk.",
"consequencedescription": "En beskrivning av vilka konsekvenser risken kan medföra om den inträffar."
}
]
```

# Exempel

### Input:

- Beskrivning av analysområde: "Implementering av ny betalningslösning för e-handel."
- Identifierade risker: 
1. Risk för driftsstopp på grund av serverproblem.
2. Risk för kunddata läcka till följd av otillräcklig datasäkerhet.

### Output:
```json
[
{
"name": "Integrationsfel med befintliga system",
"scenariodescription": "Vid integration av betalningslösningen kan tekniska problem uppstå som påverkar kommunikationen mellan plattformarna.",
"consequencedescription": "Problem med integration kan leda till att kunder inte kan genomföra köp, vilket skadar försäljning och varumärkets rykte."
},
{
"name": "Bristande användarupplevelse",
"scenariodescription": "Om användargränssnittet för den nya betalningslösningen är komplext eller svårt att förstå för kunder.",
"consequencedescription": "En dålig användarupplevelse kan leda till en ökning av avbrutna köp och därmed minskade intäkter."
}
]
```

# Notes

- Förslag ska vara relaterade till scenariot men undvika duplicering av redan angivna risker.
- Skapa unika och realistiska riskkategorier baserade på den providede informationen.
- Inkludera åtminstone två risker i svaret, men generera fler om så behövs eller begärs.
   
EOF;

      $systemprompt.= "\r\n\r\nYou are part of our company ".config('ledningssystemet.company_name', "").". The response shall be in language ".config('ledningssystemet.locale')." according to ISO 639.";

      $scopedescription = "";
      $currentrisks = [];

      switch($contextobj::class)
      {
         case \App\Models\RiskProject::class:
            foreach($contextobj->int_risks()->get() as $risk)
               $currentrisks[] = [
                  'name' => $risk->name,
                  'scenariodescription' => $risk->scenariodescription,
                  'consequencedescription' => $risk->consequencedescription,
               ];

            $scopedescription = "The context for this request is a risk workshop where the scope of the workshop is: '".$contextobj->scopedescription."' and the purpose is described as '".$contextobj->purposedescription."'.";
            break;
         case \App\Models\Asset::class:
            foreach($contextobj->int_risks()->get() as $risk)
               $currentrisks[] = [
                  'name' => $risk->name,
                  'scenariodescription' => $risk->scenariodescription,
                  'consequencedescription' => $risk->consequencedescription,
               ];

            $scopedescription = "The context for this request is an asset named ".$contextobj->name.($contextobj->description ? " with the description '".$contextobj->description."'" : "").".";

            $objprops = $contextobj->int_object_properties()->leftJoin('properties', 'properties.id', '=', 'object_properties.property_id')->select(['value', 'name'])->where('value', 1)->get();
            if($contextobj->supplier_id)
               $scopedescription .= "The asset is provided by the supplier ".$contextobj->int_supplier->name.".";

            if($objprops->count())
            {
               $scopedescription .= "The asset has the following properties: ";
               $propidx = 0;
               foreach($objprops as $prop) {
                  $scopedescription .= (($propidx++ > 0) ? "," : "").$prop->name . "='" . $prop->value . "'";
               }
            }
            $confidentialityClasses = \App\Models\ConfidentialityClass::orderBy('ordinal')->pluck('id');
            $availabilityClasses = \App\Models\AvailabilityClass::orderBy('ordinal')->pluck('id');

            $infotypes = $contextobj->int_information_types()->get();

            if($infotypes->count())
            {
               $scopedescription .= "This asset is used to handling information types: ";
               $infotypidx = 0;
               foreach($infotypes as $infotype) {
                  $scopedescription .= (($infotypidx++ > 0) ? "," : "").$infotype->name;
                  if($infotype->int_data_categories()->exists())
                     $scopedescription .= " which contains personally identifiable information";

                  $confidentiality = $infotype->int_confidentialityclass;
                  if($confidentiality) {
                     // Get the position of the confidentiality class in the list of confidentiality classes
                     $confidentialityClassPosition = $confidentialityClasses->search($confidentiality->id);
                     if($confidentialityClassPosition > 1)
                        $scopedescription .= " and is classified as highly confidential.";
                  }
                  else
                     $scopedescription .= ".";
               }
            }

            // Get the position of the availability class in the list of availability classes
            $availability = $contextobj->int_availabilityclass();
            if($availability)
            {
               $availabilityClassPosition = $availabilityClasses->search($availability->id);
               if($availabilityClassPosition > 1)
                  $scopedescription .= " The asset is classified as availability critical.";
            }

            if($contextobj->mtd)
               $scopedescription .= " The asset has a maximum tolarable downtime of ".$contextobj->mtd." hours.";

            if($contextobj->rpo)
               $scopedescription .= " The asset has a recovery point objective of ".$contextobj->rpo." hours.";


            // Determine a responsible department
            if(!$contextobj->int_responsible_user)
               abort(400, __("There must be a responsible user assigned to be able to provide risk suggestions. Please assign a responsible user and try again."));

            $userdeps = $contextobj->int_responsible_user->int_departments()->first();
            if(!$userdeps)
               abort(400, __("The responsible user must be assigned to a department in order for risk suggestions to work."));

            $contextobj->department_id = $userdeps->id;

            break;

         case \App\Models\Supplier::class:

            foreach($contextobj->int_risks()->get() as $risk)
               $currentrisks[] = [
                  'name' => $risk->name,
                  'scenariodescription' => $risk->scenariodescription,
                  'consequencedescription' => $risk->consequencedescription,
               ];

            $scopedescription = "The context for this request is a supplier named ".$contextobj->name.($contextobj->description ? " with the description '".$contextobj->description."'" : "").". We are interested in risks where the supplier can cause damage to our business, e.g. because of lacking security work, poor working conditions, environmental impact or similar.";

            $objprops = $contextobj->int_object_properties()->leftJoin('properties', 'properties.id', '=', 'object_properties.property_id')->select(['value', 'name'])->where('value', 1)->get();

            if($objprops->count())
            {
               $scopedescription .= "The supplier has the following properties: ";
               $propidx = 0;
               foreach($objprops as $prop) {
                  $scopedescription .= (($propidx++ > 0) ? "," : "").$prop->name . "='" . $prop->value . "'";
               }
            }

            $categories = $contextobj->int_supplier_categories();

            if(count($categories))
            {
               $scopedescription .= "The supplier has been categorized as follows:";
               $propidx = 0;
               foreach($categories as $cat) {
                  if($cat['applicable'])
                     $scopedescription .= (($propidx++ > 0) ? "," : "").$cat['name'] . "(" . $cat['description'] . ")";
               }
            }

            // Determine a responsible department
            if(!$contextobj->int_responsible_user)
               abort(400, __("There must be a responsible user assigned to be able to provide risk suggestions. Please assign a responsible user and try again."));

            $userdeps = $contextobj->int_responsible_user->int_departments()->first();
            if(!$userdeps)
               abort(400, __("The responsible user must be assigned to a department in order for risk suggestions to work."));

            $contextobj->department_id = $userdeps->id;
            break;

         default:
            abort(404);
      }

      $data = array(
         array(
            "role" => "system",
            "content" => $systemprompt
         ),
         array(
            "role" => "assistant",
            "content" => "Please provide information about the context for which you would like risks to be identified.",
         ),
         array(
            "role" => "user",
            "content" => $scopedescription,
         ),
         array(
            "role" => "assistant",
            "content" => "Please provide information about the risks already identified in the context.",
         ),
         array(
            "role" => "user",
            "content" => json_encode($currentrisks),
         ),
      );

      $airesponse = AIController::getAIResponse($data, __FUNCTION__);
      if(null == $airesponse)
         abort(400, __("Invalid response received from AI"));

      // Cleanup
      $riskdata = trim(str_replace('`','', $airesponse['data']));
      if(0 === strpos($riskdata, 'json'))
         $riskdata = substr($riskdata, 4);

      $riskdata = json_decode($riskdata, true);
      if(null == $riskdata)
         abort(400, __("Unfortunately the AI did not return a valid data structure."));

      $risks = [];
      foreach($riskdata as $risk){
         if(!array_key_exists('name', $risk) ||
            !array_key_exists('scenariodescription', $risk) ||
            !array_key_exists('consequencedescription', $risk))
            continue;

         $risks[] = [
            'name' => $risk['name'],
            'scenariodescription' => $risk['scenariodescription'],
            'consequencedescription' => $risk['consequencedescription'],
         ];
      }

      // Create new risks
      foreach($risks as $risk){
         $newrisk = new \App\Models\Risk();
         $newrisk->name = $risk['name'];
         $newrisk->scenariodescription = $risk['scenariodescription'];
         $newrisk->consequencedescription = $risk['consequencedescription'];
         $newrisk->department_id = $contextobj->department_id;

         // Associate with context object
         if($contextobj instanceof \App\Models\RiskProject)
            $newrisk->risk_project_id = $contextobj->id;
         else {
            $newrisk->context_type = $contextobj::class;
            $newrisk->context_id = $contextobj->id;
         }
         $newrisk->riskowner_id = $contextobj->responsible_user_id;
         $newrisk->save();

         // Create new risk note
         \App\Models\ActivityLog::addMessage(__("The risk was AI-generated on behalf of")." ".auth()->user()->name, $newrisk);
      }

      return response()->json(array('riskcount' => count($risks)));
   }

   public static function chatSendStream($threadId = null)
   {
      $user = auth()->user();
      if(!$user || !$user->can('useai'))
         abort(403);

      $userMessageInput = trim((string) request()->input('message', ''));
      if('' === $userMessageInput)
         abort(400, __('A message is required.'));

      if(8000 < mb_strlen($userMessageInput))
         abort(400, __('The message is too long.'));

      $conversation = [[
         'role' => 'system',
         'content' => __('You are a helpful assistant for our company :company and reside in our application :appname. The person asking is :username. Answer :username directly and do not ask follow-up questions. If details are missing, state your assumptions and still provide the best possible answer. Respond in language :lang according to ISO 639 unless the user explicitly asks for another language.', [
            'company' => config('ledningssystemet.company_name', ''),
            'lang' => config('ledningssystemet.locale', 'en'),
            'appname' => config('ledningssystemet.application_name', 'Ledningssystemet.se'),
            'username' => auth()->user()->name,
         ]),
      ]];

      $conversation[] = [
         'role' => 'user',
         'content' => $userMessageInput,
      ];

      $payload = [
         'messages' => $conversation,
         'max_completion_tokens' => config('ledningssystemet.openai_max_completion_tokens', 32768),
         'temperature' => config('ledningssystemet.openai_temperature', 0.3),
         'top_p' => config('ledningssystemet.openai_top_p', 1),
         'frequency_penalty' => config('ledningssystemet.openai_frequency_penalty', -0.2),
         'presence_penalty' => config('ledningssystemet.openai_presence_penalty', -0.2),
         'model' => config('ledningssystemet.openai_model', ''),
         'stream' => true,
         'stream_options' => ['include_usage' => true],
      ];

      return response()->stream(function() use ($payload, $user) {
         @ini_set('zlib.output_compression', 0);
         @ini_set('output_buffering', 'off');

         while(ob_get_level() > 0)
            ob_end_flush();

         $emit = function($event, $data) {
            echo 'event: '.$event."\n";
            echo 'data: '.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
            @ob_flush();
            flush();
         };

         $buffer = '';
         $assistantContent = '';
         $modelName = config('ledningssystemet.openai_model', '');
         $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
         $stoppedByClient = false;
         $responseStatus = 'streaming';

         $emit('meta', ['message_id' => null]);

         $openAiEndpoint = config('ledningssystemet.openai_endpoint');
         if(!$openAiEndpoint)
         {
            $responseStatus = 'error';
            $emit('error', ['message' => __('Failed to initialize AI request.')]);
            return;
         }

         $response = self::openAiRequest(true)
            ->withHeaders([
               'Accept' => 'text/event-stream',
            ])
            ->post($openAiEndpoint, $payload);

         $statusCode = $response->status();
         $curlError = '';

         if($response->successful())
         {
            $stream = $response->toPsrResponse()->getBody();
            while(!$stream->eof())
            {
               if(connection_aborted())
               {
                  $stoppedByClient = true;
                  break;
               }

               $chunk = $stream->read(8192);
               if('' === $chunk)
               {
                  usleep(10000);
                  continue;
               }

               $buffer .= $chunk;
               while(false !== ($lineEnd = strpos($buffer, "\n")))
               {
                  $line = trim(substr($buffer, 0, $lineEnd));
                  $buffer = substr($buffer, $lineEnd + 1);

                  if('' === $line || 0 !== strpos($line, 'data: '))
                     continue;

                  $raw = substr($line, 6);
                  if('[DONE]' === $raw)
                     continue;

                  $json = json_decode($raw, true);
                  if(!is_array($json))
                     continue;

                  if(array_key_exists('model', $json) && is_string($json['model']))
                     $modelName = $json['model'];

                  if(array_key_exists('usage', $json) && is_array($json['usage']))
                  {
                     $usage['prompt_tokens'] = intval($json['usage']['prompt_tokens'] ?? 0);
                     $usage['completion_tokens'] = intval($json['usage']['completion_tokens'] ?? 0);
                     $usage['total_tokens'] = intval($json['usage']['total_tokens'] ?? 0);
                  }

                  $delta = $json['choices'][0]['delta']['content'] ?? '';
                  if(!is_string($delta) || '' === $delta)
                     continue;

                  $assistantContent .= $delta;
                  $emit('delta', ['content' => $delta]);
               }
            }
         }
         else
         {
            $curlError = $response->body();
         }

         if($stoppedByClient)
         {
            $responseStatus = 'aborted';
            return;
         }

         if(200 !== $statusCode)
         {
            $responseStatus = 'error';

            Log::warning('AI stream call failed', ['status_code' => $statusCode, 'error' => $curlError]);
            $emit('error', ['message' => __('The AI request failed. Please try again.')]);
            return;
         }

         $responseStatus = 'completed';

         DB::table('ai_queries')->insert([
            'prompt_tokens' => max(0, intval($usage['prompt_tokens'])),
            'completion_tokens' => max(0, intval($usage['completion_tokens'])),
            'total_tokens' => max(0, intval($usage['total_tokens'])),
            'model' => $modelName ?: config('ledningssystemet.openai_model', ''),
            'context' => 'chatSendStreamSingleTurn',
            'user_id' => $user->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
         ]);

         $emit('done', ['message_id' => null, 'status' => $responseStatus]);
      }, 200, [
         'Content-Type' => 'text/event-stream',
         'Cache-Control' => 'no-cache, no-transform',
         'X-Accel-Buffering' => 'no',
      ]);
   }

   private static function buildStructuredChatContextLines($userMessageInput)
   {
      $queryText = mb_strtolower(trim((string) $userMessageInput));
      if('' === $queryText)
         return [];

      $lines = [];
      $today = date('Y-m-d');

      $wantsHighestRisks = self::queryLooksLikeHighestRiskQuestion($queryText);
      if($wantsHighestRisks)
      {
         $riskRows = \App\Models\Risk::query()
            ->leftJoin('risk_level_mappings as rlm', function($join) {
               $join->on('rlm.probability_level_id', '=', 'risks.probability_id')
                  ->on('rlm.consequence_level_id', '=', 'risks.consequence_id');
            })
            ->leftJoin('risk_levels as rl', 'rl.id', '=', 'rlm.risk_level_id')
            ->whereNull('risks.replacedby_id')
            ->whereNotNull('risks.probability_id')
            ->whereNotNull('risks.consequence_id')
            ->select(['risks.*', 'rl.name as rag_risk_level_name', 'rl.ordinal as rag_risk_level_ordinal'])
            ->orderByDesc('rl.ordinal')
            ->orderBy('risks.id')
            ->limit(120)
            ->get();

         $visibleRisks = [];
         foreach($riskRows as $riskRow)
         {
            if(auth()->user()->cannot('view', $riskRow))
               continue;

            if(null === $riskRow->rag_risk_level_ordinal)
               continue;

            $visibleRisks[] = $riskRow;
         }

         $highestOrdinal = null;
         foreach($visibleRisks as $riskRow)
         {
            $ordinal = intval($riskRow->rag_risk_level_ordinal);
            if((null === $highestOrdinal) || ($ordinal > $highestOrdinal))
               $highestOrdinal = $ordinal;
         }

         $highestRisks = [];
         if(null !== $highestOrdinal)
         {
            foreach($visibleRisks as $riskRow)
            {
               if(intval($riskRow->rag_risk_level_ordinal) !== $highestOrdinal)
                  continue;
               $highestRisks[] = $riskRow;
            }
         }

         $header = "Source: StructuredQuery#1\n"
            ."Intent: highest-risks\n"
            ."Date: ".$today."\n"
            ."Visible highest-level risks: ".count($highestRisks)."\n";

         if(0 === count($highestRisks))
            $header .= "No visible risks with mapped risk level were found in this structured query.\n";
         else
         {
            foreach(array_slice($highestRisks, 0, 20) as $riskRow)
            {
               $header .= "- [Risk-".$riskRow->id."] "
                  ."Level: ".(string) $riskRow->rag_risk_level_name." (".intval($riskRow->rag_risk_level_ordinal).")"
                  .", Name: ".trim((string) $riskRow->name_pretty)."\n";
            }
         }

         $lines[] = trim($header);
      }

      $wantsOverdueActions = self::queryLooksLikeOverdueActionQuestion($queryText);
      if($wantsOverdueActions)
      {
         $actionRows = \App\Models\ControlAction::query()
            ->whereNull('finished_at')
            ->whereNotNull('due')
            ->where('due', '<', $today)
            ->orderBy('due')
            ->orderBy('id')
            ->limit(120)
            ->get();

         $visibleActions = [];
         foreach($actionRows as $actionRow)
         {
            if(auth()->user()->cannot('view', $actionRow))
               continue;

            $visibleActions[] = $actionRow;
         }

         $header = "Source: StructuredQuery#2\n"
            ."Intent: overdue-control-actions\n"
            ."Date: ".$today."\n"
            ."Visible overdue control actions: ".count($visibleActions)."\n";

         if(0 === count($visibleActions))
            $header .= "No visible overdue control actions were found in this structured query.\n";
         else
         {
            foreach(array_slice($visibleActions, 0, 20) as $actionRow)
            {
               $header .= "- [ControlAction-".$actionRow->id."] "
                  ."Due: ".$actionRow->due
                  .", Name: ".trim((string) $actionRow->name)."\n";
            }
         }

         $lines[] = trim($header);
      }

      return $lines;
   }

   private static function queryLooksLikeHighestRiskQuestion($queryText)
   {
      return self::queryContainsAny($queryText, [
         'storst',
         'störst',
         'hogs',
         'hög',
         'hogsta risk',
         'högsta risk',
         'hogst risk',
         'högst risk',
         'largest risk',
         'highest risk',
         'top risk',
         'biggest risk',
         'riskniv',
         'risknivå',
      ]);
   }

   private static function queryLooksLikeOverdueActionQuestion($queryText)
   {
      return self::queryContainsAny($queryText, [
         'forfall',
         'förfall',
         'förfallen',
         'förfallna',
         'forsen',
         'försen',
         'overdue',
         'past due',
         'sen handlingsplan',
         'handlingsplan',
         'control action',
      ]);
   }

   private static function queryContainsAny($haystack, $needles)
   {
      foreach($needles as $needle)
      {
         if(false !== strpos($haystack, $needle))
            return true;
      }

      return false;
   }

   /* Generic AI endpoint call */
   public static function getAIResponse($input = [], $context = 'misc', $responseformat = null)
   {
      // Ensure user is allowed to use AI features
      if(!auth()->user()->can('useai'))
         abort(403);

      // Don't waste tokens on nothing
      if(0 == count($input))
         return null;

      $data = array(
         'messages' => $input,
         'max_completion_tokens' => config('ledningssystemet.openai_max_completion_tokens', 32768 ),
         'temperature' => config('ledningssystemet.openai_temperature', 1),
         'top_p' => config('ledningssystemet.openai_top_p', 1),
         'frequency_penalty' => config('ledningssystemet.openai_frequency_penalty', -0.2),
         'presence_penalty' => config('ledningssystemet.openai_presence_penalty', -0.2),
         'model' => config('ledningssystemet.openai_model', ""),
      );

      if($responseformat)
         $data['response_format'] = $responseformat;

      $endpoint = config('ledningssystemet.openai_endpoint');
      if(!$endpoint)
         throw new \Exception('OPENAI endpoint is not configured');

      $response = self::openAiRequest()->post($endpoint, $data);
      $retcode = $response->status();
      $retval = $response->body();

      // Validate a 200 response
      if(200 != $retcode) {
         $errorBody = json_decode($retval, true);
         $upstreamError = (is_array($errorBody) && array_key_exists('error', $errorBody) && is_array($errorBody['error']) && array_key_exists('message', $errorBody['error']))
            ? $errorBody['error']['message']
            : __("Unknown upstream error");

         abort(400, __("The AI Agent did not return a valid response: :message", ['message' => $upstreamError]));
      }
      // Parse response
      $serverresponse = json_decode($retval, true);
      if(!is_array($serverresponse))
         throw new \Exception('Invalid response (non-JSON) received from server. Return code '.$retcode);

      $prompttokens = $serverresponse['usage']['prompt_tokens'];
      $completiontokens = $serverresponse['usage']['completion_tokens'];
      $totaltokens = $serverresponse['usage']['total_tokens'];
      $modelname = $serverresponse['model'];

      DB::table('ai_queries')->insert([
         'prompt_tokens' => $prompttokens,
         'completion_tokens' => $completiontokens,
         'total_tokens' => $totaltokens,
         'model' => $modelname,
         'context' => $context,
         'user_id' => auth()->user()->id,
         'created_at' => date("Y-m-d H:i:s"),
         'updated_at' => date("Y-m-d H:i:s"),
      ]);

      // Ensure choices exist
      if(!array_key_exists('choices', $serverresponse) ||
         !is_array($serverresponse['choices']) ||
         (0 == count($serverresponse['choices'])) ||
         (!array_key_exists('finish_reason', $serverresponse['choices'][0])))
         abort(400, __("The AI Agent did not return a valid response, so unfortunately you are on your own"));
         
      // Check stop reason
      if("stop" != $serverresponse['choices'][0]['finish_reason'])
         abort(400, __("The AI Agent did not return a valid response, so unfortunately you are on your own"));

      // Return
      return(['data' => $serverresponse['choices'][0]['message']['content']]);
   }

   protected static function openAiRequest(bool $stream = false): PendingRequest
   {
      $request = Http::acceptJson()
         ->withToken(config('ledningssystemet.openai_api_key', ''))
         ->timeout($stream ? 180 : 120)
         ->retry(2, 250);

      if($stream)
         $request = $request->withOptions(['stream' => true]);

      if('local' == app()->environment())
         $request = $request->withOptions(['verify' => false]);

      return $request;
   }
 
}
