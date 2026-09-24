<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use SimpleXMLElement;
use Illuminate\Support\Facades\DB;
use App\Models\Process;
use App\Models\ProcessActivity;
use App\Models\InformationType;
use App\Models\Asset;
use Illuminate\Validation\ValidationException;

class ProcessController extends Controller
{
   protected $process;
   protected $pam;
   protected $publish;
   protected $save;
   protected $validate;
   
   // Save process
   public function save(Request $request)
   {
      try
      {
         $this->publish = $request->has("publish") && ("true" == $request->input("publish"));
         $this->save = $request->has("save") && ("true" == $request->input("save"));
         $this->validate = $request->has("validate") && ("true" == $request->input("validate"));
         
         // Fetch process
         $this->process = Process::findOrFail($request->id);
         
         // Authorize action
         $this->authorize('update', $this->process);

         
         // Load BPMN XML
         $bpmnxml = new \XMLReader();
         $bpmnxml->XML($request->xml);
         
         // Save XML
         $this->process->bpmn = $request->xml;
         if($this->save || $this->publish)
            $this->process->save();
         
         // Return here if save only
         if($this->save &&
            !$this->publish &&
            !$this->validate)
            return response()->json(array('info' =>  __("The processchart was successfully saved")));
         
         // Create abstraction model based on bpmn
         $this->pam = new ProcessAbstractModel($this->process->bpmn);

         // Save abstraction model
         $this->saveModel();
         
         // If successful, save svg and published bpmn
         if($this->publish)
         {
            $this->process->svg = $request->svg;
            $this->process->publishedbpmn = $this->process->bpmn;
            $this->process->save();
         }
         
         // Report back
         $retmessage = "";
         if($this->publish)
            $retmessage = __("The processchart was successfully saved, validated and published");
         else if($this->save)
            $retmessage = __("The processchart was successfully saved and validated");
         else if($this->validate)
            $retmessage = __("The processchart was validated without errors");
            
         return response()->json(array('info' => $retmessage));
      }
      catch(ValidationException $ex)
      {
         // Special case: An empty validationexception is thrown before saving info to the database. Utilized during the validation phase in order to make sure that a DB error will not be thrown if trying to publish.
         if("" == $ex->getMessage())
            return response()->json(array('info' => __("The processchart was validated without errors")));
         
         $errorstring = "";
         foreach($ex->errors() as $err)
            $errorstring .= (("" != $errorstring) ? "<br>" : "").implode('<br>', $err);
         
         // Return info
         if($this->publish)
            return response()->json(array('warning' => $errorstring));
         else
            return response()->json(array('info' => $errorstring));
      }
   }
   
   // LOAD XML from latest draft
   public function loadxml(Request $request)
   {
      // Fetch process
      $process = \App\Models\Process::findOrFail($request->id);
      
      // If there is no bpmn-file, use an empty one
      $bpmn = $process->bpmn;
      if("" == $process->bpmn)
         $bpmn = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><bpmn:definitions xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:bpmn=\"http://www.omg.org/spec/BPMN/20100524/MODEL\" xmlns:bpmndi=\"http://www.omg.org/spec/BPMN/20100524/DI\" targetNamespace=\"http://bpmn.io/schema/bpmn\"> <bpmn:process id=\"Process\" isExecutable=\"false\" /><bpmndi:BPMNDiagram id=\"BPMNDiagram\">    <bpmndi:BPMNPlane id=\"BPMNPlane\" bpmnElement=\"Process\" /></bpmndi:BPMNDiagram></bpmn:definitions>";

      $retval = array('bpmn' => $bpmn);
      
      // Load existing items
      $retval['processes'] = [];
      $retval['activities'] = [];
      $retval['informationtypes'] = [];
      $retval['assets'] = [];
      
      foreach(\App\Models\Process::where('id', '<>', $process->id)->get() as $obj)
         $retval['processes'][] = array('id' => $obj->id, 'bpmnid' => null, 'name' => $obj->name);
         
      foreach($process->int_process_activities as $obj)
         $retval['activities'][] = array('id' => $obj->id, 'bpmnid' => $obj->bpmnId, 'name' => $obj->name);
      
      foreach(\App\Models\InformationType::get() as $obj)
         $retval['informationtypes'][] = array('id' => $obj->id, 'bpmnid' => null, 'name' => $obj->name);
         
      foreach(\App\Models\Asset::get() as $obj)
         $retval['assets'][] = array('id' => $obj->id, 'bpmnid' => null, 'name' => $obj->name);

      return $retval;
   }
   
   
   public function saveModel()
   {
      // Begin transaction. If an exception is called, the changes are to be rolled back
      DB::transaction(function(){
         /* Process activities */
         
         // Load current process activities
         foreach($this->process->int_process_activities as $pa)
         {
            try
            {
               // Look up any existing pa's
               $pamElem = $this->pam->getElementById($pa->bpmnId);
               
               // If not found, then simply delete it from the database
               if(null == $pamElem)
               {
                  $pa->delete();
               }
               else
               {
                  $pamElem->dbObject = $pa;
                  
                  // It was found. Shall its name or ordinal be updated?
                  if(($pamElem->name != $pa->name) ||
                     ($pamElem->ordinal != $pa->ordinal))
                  {
                     $pa->name = $pamElem->name;
                     $pa->ordinal = $pamElem->ordinal;
                     $pa->save();
                  }
               }
            }
            catch(ValidationException $ex)
            {
               throw ValidationException::withMessages(["Task '".$pa->name."': ".$ex->getMessage()]);
            }
         }
         
         // Create any non-existing pa's
         foreach($this->pam->getElementsByType('activity') as &$activity)
         {
            if(property_exists($activity, 'dbObject'))
               continue;
            
            try
            {
               $newObj = new ProcessActivity;
               $newObj->name = $activity->name;
               $newObj->bpmnId = $activity->id[0];
               $newObj->ordinal = $activity->ordinal;
               $this->process->int_process_activities()->save($newObj);
               $activity->dbObject = $newObj;
            }
            catch(ValidationException $ex)
            {
               throw ValidationException::withMessages(["Task '".$activity->name."': ".$ex->getMessage()]);
            }
         }
         
         /* Information types */
         foreach($this->pam->getElementsByType('informationtype') as &$infotype)
         {
            // Try to lookup information type in the register
            $dbInfoType = InformationType::where('name', 'LIKE', $infotype->name)->first();
            
            // Nothing found, create it
            if(null == $dbInfoType)
            {
               try
               {
                  $dbInfoType = new InformationType;
                  $dbInfoType->name = $infotype->name;
                  $dbInfoType->save();
               }
               catch(ValidationException $ex)
               {
                  throw ValidationException::withMessages(["Information type '".$infotype->name."': ".$ex->getMessage()]);
               }
            }
            
            // Associate object
            $infotype->dbObject = $dbInfoType;
            
            // Create connection to the process activities
            foreach(array_merge($infotype->activitiesin, $infotype->activitiesout) as $activity)
            {
               // Check if there already exist a relation
               if(!$dbInfoType->int_process_activities()->where('process_activities.id', '=', $activity->dbObject->id)->exists())
                  $dbInfoType->int_process_activities()->attach($activity->dbObject);
            }
         }

         // Delete infotype mappings no longer valid
         foreach($this->pam->getElementsByType('activity') as &$activity)
         {
            foreach($activity->dbObject->int_information_types as $infotype)
            {
               $found = false;
               foreach(array_merge($activity->informationtypesin, $activity->informationtypesout) as $activityitm)
               {
                  if($activityitm->dbObject->id == $infotype->id)
                  {
                     $found = true;
                     break;
                  }
               }
               
               if(!$found)
                  $activity->dbObject->int_information_types()->detach($infotype);
            }
         }
         
         /* Assets */
         $assetinfotypeassociations = [];
         foreach($this->pam->getElementsByType('asset') as &$asset)
         {
            // Try to lookup asset in the register
            $dbAsset = Asset::where('name', 'LIKE', $asset->name)->first();
            
            // Nothing found, create it
            if(null == $dbAsset)
            {
               try
               {
                  $dbAsset = new Asset;
                  $dbAsset->name = $asset->name;
                  $dbAsset->save();
               }
               catch(ValidationException $ex)
               {
                  throw ValidationException::withMessages(["Asset '".$dbAsset->name."': ".$ex->getMessage()]);
               }
            }
            
            // Associate object
            $asset->dbObject = $dbAsset;
            
            // Create connection to information types
            foreach($asset->informationtypes as $infotype)
            {
               // Check if there already is an association, otherwise create it
               $isAssociated = false;
               if(0 < $dbAsset->int_information_types()->where('information_type_id', '=', $infotype->dbObject->id)->where('process_id', '=', $this->process->id)->count())
                  $isAssociated  = true;
               
               if(!$isAssociated)
                  $dbAsset->int_information_types()->attach($infotype->dbObject, ['process_id' => $this->process->id]);
               
               $assetinfotypeassociations[] = array('asset_id' => $asset->dbObject->id, 'information_type_id' => $infotype->dbObject->id);
            }
         }
         
         // Check if there are existing mappings that shall no longer be included (i.e. where the asset is removed from the process)
         foreach($this->process->int_assets as $asset)
         {
            $infotypes = array();
            foreach($assetinfotypeassociations as $obj)
            {
               if($obj['asset_id'] == $asset->id)
                  $infotypes[$obj['information_type_id']] = $obj['information_type_id'];
            }
            
            // Check if any associations shall be detached
            DB::table('asset_information_type')->where('process_id', $this->process->id)->where('asset_id', $asset->id)->whereNotIn('information_type_id', $infotypes)->delete();
         }
         
         /* Processlinks */

         // Collect list of processes to be linked
         $processLinks = [];
         foreach($this->pam->getElementsByType('processlink') as &$processlink)
         {
            // Look up the process
            $process = Process::where('name', $processlink->name)->firstOrFail();
            $processLinks[$process->id] = $process->id;
         }         
         
         // Sync
         $this->process->int_linked_processes()->sync($processLinks);
         
         if(!$this->save)
         {
            throw ValidationException::withMessages([""]);
         }
      });
   }   
}

class ProcessAbstractModel {
   
   protected $elements = [];
   protected $associations = [];
   protected $pxe;
   
   function __construct($bpmn)
   {
      /* Load BPMN chart as XML */
      $this->pxe = new SimpleXMLElement($bpmn);
      
      // Register namespaces
      $this->pxe->registerXPathNamespace('xsi', "http://www.w3.org/2001/XMLSchema-instance");
      $this->pxe->registerXPathNamespace('bpmn', "http://www.omg.org/spec/BPMN/20100524/MODEL");
      $this->pxe->registerXPathNamespace('bpmndi', "http://www.omg.org/spec/BPMN/20100524/DI");
      $this->pxe->registerXPathNamespace('dc', "http://www.omg.org/spec/DD/20100524/DC");

      // Register all elements and associations in the process
      foreach($this->pxe->xpath('/bpmn:definitions/bpmn:process/*') as $node)
      {
         // Validate and derive ID of object
         $newObj = new \stdClass();
         
         $id = $node['id'];
         if((null == $id) ||
            ("" == trim($id)))
            throw new Exception('No ID was provided for element of type '.$node->getName());
         
         $newObj->id = [];
         $newObj->id[] = (string)$id;
         
         // Derive and sanitize object name
         $newObj->name = $node['name'];
         if(null == $newObj->name)
            $newObj->name = "";
         else
            $newObj->name = trim((string)$newObj->name);
         
         // Validate name
         if(htmlspecialchars($newObj->name) != $newObj->name)
            throw ValidationException::withMessages(['\''.htmlspecialchars($newObj->name).'\' '.__("is not a valid name. It contains special characters which are not allowed.")]);
         
         // Create artefacts by types
         switch(strtolower($node->getName()))
         {
            // Types to simply ignore...
            case 'dataobject':
               break;
               
            // Types to save
            case 'task': // Activity
               // Validate that a name exist and is unique
               if(0 == strlen($newObj->name))
                  throw ValidationException::withMessages([__('There are one ore more activities that has not been provided a valid name')]);
               
               if(null != $this->getElementByName('activity', $newObj->name))
                  throw ValidationException::withMessages([__('There is at least one activity that has been provided a non-unique name').' '.'('.$newObj->name.')']);
                  
               // Store the new object
               $newObj->type = 'activity';
               $newObj->informationtypesout = [];
               $newObj->informationtypesin = [];
               $newObj->elementsbefore = [];
               $newObj->elementsafter = [];
               $newObj->ordinal = -1;
               
               $this->elements[] = $newObj;
               
               // Get any data input associations to create virtual dataflows
               foreach($node->xpath('bpmn:dataInputAssociation') as $da)
               {
                  foreach( $da->xpath('bpmn:sourceRef') as $srcRef)
                  {
                     $newAssociation = new \stdClass();
                     $newAssociation->id = (string)$da['id'];
                     $newAssociation->src = (string)$srcRef;
                     $newAssociation->dst = (string)$newObj->id[0];
                     $newAssociation->type = 'dataflow';
                     $this->associations[] = $newAssociation;
                  }
               }

               // Get any data output associations to create virtual dataflows
               foreach($node->xpath('bpmn:dataOutputAssociation') as $da)
               {
                  foreach( $da->xpath('bpmn:targetRef') as $dstRef)
                  {
                     $newAssociation = new \stdClass();
                     $newAssociation->id = (string)$da['id'];
                     $newAssociation->dst = (string)$dstRef;
                     $newAssociation->src = (string)$newObj->id[0];
                     $newAssociation->type = 'dataflow';
                     $this->associations[] = $newAssociation;
                  }
               }
               break;
               
            case 'subprocess': // Sub process
               // Validate that a name exist and is indeed a valid process name
               if(0 == strlen($newObj->name))
                  throw ValidationException::withMessages([__('There are one ore more process links that has not been provided a valid name. The name must be equal to an existing process.')]);
               
               // Look up the process
               if(null == Process::where('name', $newObj->name)->first())
                  throw ValidationException::withMessages([__('There is a process link refererring to a non-existing process').' ('.$newObj->name.')']);
               
               // Store the new object
               $newObj->type = 'processlink';
               $newObj->elementsbefore = [];
               $newObj->elementsafter = [];
               
               $this->elements[] = $newObj;
               break;  
               
            case 'dataobjectreference': // Information type
               // Validate that a name exist
               if(0 == strlen($newObj->name))
                  throw ValidationException::withMessages([__('There are one ore more information types that has not been provided a valid name')]);
               
               // Check if element already exists, if so simply add the new id to the existing element, otherwise create new element
               $curelem = $this->getElementByName('informationtype', $newObj->name);
               if(null == $curelem)
               {
                  // Store the new object
                  $newObj->type = 'informationtype';
                  $newObj->activitiesin = [];
                  $newObj->activitiesout = [];
                  $newObj->assets = [];
                  $this->elements[] = $newObj;
               }
               else
                  $curelem->id[] = $newObj->id[0];
                  
               break;
            case 'datastorereference': // Asset
               // Validate that a name exist
               if(0 == strlen($newObj->name))
                  throw ValidationException::withMessages([__('There are one ore more assets that has not been provided a valid name')]);
               
               // Check if element already exists, if so simply add the new id to the existing element, otherwise create new element
               $curelem = $this->getElementByName('asset', $newObj->name);
               if(null == $curelem)
               {
                  // Store the new object
                  $newObj->type = 'asset';
                  $newObj->informationtypes= [];
                  $this->elements[] = $newObj;
               }
               else
                  $curelem->id[] = $newObj->id[0];
               break;
               
            case 'exclusivegateway': // Gateway
               $newObj->type = 'gateway';
               $newObj->elementsbefore = [];
               $newObj->elementsafter = [];
               $this->elements[] = $newObj;
               break;
               
            case 'sequenceflow': // Flow from one artefact to another
               $newObj->type = 'sequenceflow';
               $newObj->src = $node['sourceRef'];
               $newObj->dst = $node['targetRef'];
               $this->associations[] = $newObj;
               break;
               
            case 'association': // Association for infotypes and assets
               $newObj->type = 'association';
               $newObj->src = $node['sourceRef'];
               $newObj->dst = $node['targetRef'];
               $this->associations[] = $newObj;
               break;
               
            case 'startevent': // Start event
               $newObj->type = 'start';
               $newObj->elementsbefore = [];
               $newObj->elementsafter = [];
               $this->elements[] = $newObj;
               break;
               
            case 'endevent': // End event
               $newObj->type = 'end';
               $newObj->elementsbefore = [];
               $newObj->elementsafter = [];
               $this->elements[] = $newObj;
               break;
            case 'textannotation': // Text annotation
               $newObj->type = 'textannotation';
               $this->elements[] = $newObj;
               break;
            default:
               throw new Exception('Unknown process artefact type '.$node->getName());
         }
      }
      
      // Dissolve all associations into element pointers
      foreach(array_keys($this->associations) as $assockey)
      {
         $srcObj = $this->getElementById($this->associations[$assockey]->src);
         $dstObj = $this->getElementById($this->associations[$assockey]->dst);
         
               
         // Validate existing src and dst objects
         if((null == $srcObj) ||
            (null == $dstObj))
         {
           unset($this->associations[$assockey]);
           continue;
         }
         
         switch($this->associations[$assockey]->type)
         {
            case 'dataflow':
               // Store association
               if(("activity" == $srcObj->type) &&
                  ("informationtype" == $dstObj->type))
               {
                  $srcObj->informationtypesout[] = $dstObj;
                  $dstObj->activitiesin[] = $srcObj;
               }
               else if(("activity" == $dstObj->type) &&
                  ("informationtype" == $srcObj->type))
               {
                  $dstObj->informationtypesin[] = $srcObj;
                  $srcObj->activitiesout[] = $dstObj;
               }
               else
                  throw new Exception('Error in association '.$this->associations[$assockey]->id.': Trying to connect invalid objects between '.$srcObj->type.' and '.$dstObj->type);
               
               unset($this->associations[$assockey]);
               break;
            case 'sequenceflow':
               $srcObj->elementsafter[] = $dstObj;
               $dstObj->elementsbefore[] = $srcObj;
               
               unset($this->associations[$assockey]);
               break;
            case 'association':
               // Store association
               if(("informationtype" == $srcObj->type) &&
                  ("asset" == $dstObj->type))
               {
                  $srcObj->assets[] = $dstObj;
                  $dstObj->informationtypes[] = $srcObj;
               }
               else if(("informationtype" == $dstObj->type) &&
                  ("asset" == $srcObj->type))
               {
                  $dstObj->assets[] = $srcObj;
                  $srcObj->informationtypes[] = $dstObj;
               }
               else if(("textannotation" == $dstObj->type) ||
                  ("textannotation" == $srcObj->type))
               {
               }
               else
                  throw new Exception('Error in association '.$this->associations[$assockey]->id.': Trying to connect invalid objects between '.$srcObj->type.' and '.$dstObj->type);
               
               unset($this->associations[$assockey]);
               break;
            default:
               throw new Exception('Unknown association type '.$this->associations[$assockey]->type);
         }
         
      }

      // Remove gateways, events, textannotations and subprocesses
      foreach(array_keys($this->elements) as $elemkey)
      {
         if("start" == $this->elements[$elemkey]->type)
         {
            foreach($this->elements[$elemkey]->elementsafter as $elem)
            {
               foreach(array_keys($elem->elementsbefore) as $elembeforekey)
               {
                  if($elem->elementsbefore[$elembeforekey]->id == $this->elements[$elemkey]->id)
                  {
                     unset($elem->elementsbefore[$elembeforekey]);
                  }
               }
            }
            unset($this->elements[$elemkey]);
         }
         else if("end" == $this->elements[$elemkey]->type)
         {
            foreach($this->elements[$elemkey]->elementsbefore as $elem)
            {
               foreach(array_keys($elem->elementsafter) as $elemafterkey)
               {
                  if($elem->elementsafter[$elemafterkey]->id == $this->elements[$elemkey]->id)
                  {
                     unset($elem->elementsafter[$elemafterkey]);
                  }
               }
            }
            unset($this->elements[$elemkey]);
         }
         else if("gateway" == $this->elements[$elemkey]->type)
         {
            $elemsbefore = $this->elements[$elemkey]->elementsbefore;
            $elemsafter = $this->elements[$elemkey]->elementsafter;

            // Connect all elements after with all elements before this one
            foreach($elemsafter as $dstelem)
            {
               // Remove associations to this element
               foreach(array_keys($dstelem->elementsbefore) as $dstelembeforekey)
               {
                  if($dstelem->elementsbefore[$dstelembeforekey]->id == $this->elements[$elemkey]->id)
                     unset($dstelem->elementsbefore[$dstelembeforekey]);
               }
               
               // Create associations with all the before-elements
               foreach($elemsbefore as $srcelem)
               {
                  if($srcelem->id != $dstelem->id)
                  {
                     $dstelem->elementsbefore[] = $srcelem;
                     $srcelem->elementsafter[] = $dstelem;
                  }
               }
            }

            // Clean up any references left in before-elements
            foreach($elemsbefore as $srcelem)
            {
               foreach(array_keys($srcelem->elementsafter) as $srcelemafterkey)
               {
                  if($srcelem->elementsafter[$srcelemafterkey]->id == $this->elements[$elemkey]->id)
                     unset($srcelem->elementsafter[$srcelemafterkey]);
               }
            }

            unset($this->elements[$elemkey]);
         }
         else if("textannotation" == $this->elements[$elemkey]->type)
         {
            unset($this->elements[$elemkey]);
         }
         else if("processlink" == $this->elements[$elemkey]->type)
         {
            $elemsbefore = $this->elements[$elemkey]->elementsbefore;
            $elemsafter = $this->elements[$elemkey]->elementsafter;
            
            // Connect all elements after with all elements before this one
            foreach($elemsafter as $dstelem)
            {
               // Remove associations to this element
               foreach(array_keys($dstelem->elementsbefore) as $dstelembeforekey)
               {
                  if($dstelem->elementsbefore[$dstelembeforekey]->id == $this->elements[$elemkey]->id)
                     unset($dstelem->elementsbefore[$dstelembeforekey]);
               }
               
               // Create associations with all the before-elements
               foreach($elemsbefore as $srcelem)
               {
                  $dstelem->elementsbefore[] = $srcelem;
                  $srcelem->elementsafter[] = $dstelem;
               }
            }

            // Clean up any references left in before-elements
            foreach($elemsbefore as $srcelem)
            {
               foreach(array_keys($srcelem->elementsafter) as $srcelemafterkey)
               {
                  if($srcelem->elementsafter[$srcelemafterkey]->id == $this->elements[$elemkey]->id)
                     unset($srcelem->elementsafter[$srcelemafterkey]);
               }
            }
         }         
      }
      
      /* Update ordinals */
      // Get hold of all start elements (i.e. no elements before)
      $startElements = [];
      foreach(array_keys($this->elements) as $elemkey)
      {
         if('activity' != $this->elements[$elemkey]->type)
            continue;

         if( 0 == count($this->elements[$elemkey]->elementsbefore) )
            $startElements[] = &$this->elements[$elemkey];
      }
      
      // Perform a recursive assignment of orders
      $ordinal = 0;
      function assignFcn($elem, $ordinal, $checkedItems = [])
      {
         if(in_array($elem->id, $checkedItems))
            return;
         else
            $checkedItems[] = $elem->id;
         
         $retval = $ordinal;
         $elem->ordinal = $ordinal++;
         foreach($elem->elementsafter as $afterelem)
            $retval = assignFcn($afterelem, $ordinal, $checkedItems);
            
         return $retval;
      };

      foreach($startElements as $elem)
      {
         $ordinal++;
         $ordinal = assignFcn($elem, $ordinal);
      }

      /* Perform ruleset validations */
      $ruleErrors = [];
      
      foreach($this->elements as $elem)
      {
         // Rule 1: An information type must be associated with at least one activity
         if(('informationtype' == $elem->type) &&
            (0 == count($elem->activitiesin)) &&
            (0 == count($elem->activitiesout)))
            $ruleErrors[] = __("Information type").' '.$elem->name.' '.__('is not connected to any activities');
            
         // Rule 2: An information type must be associated with at least one asset
         if(('informationtype' == $elem->type) &&
            (0 == count($elem->assets)))
            $ruleErrors[] = __("Information type")." ".$elem->name." ".__("is not connected to any assets");
         
         // Rule 3: An asset must be associated with at least one information type
         if(('asset' == $elem->type) &&
            (0 == count($elem->informationtypes)))
            $ruleErrors[] = __("Asset")." ".$elem->name." ".__("is not connected to any information types");
      }
      
      if(0 < count($ruleErrors))
      {
         throw ValidationException::withMessages($ruleErrors);
      }
   }
   
   public function getElementById($id)
   {
      foreach($this->elements as &$elem)
      {
         foreach($elem->id as $elemid)
         {
            if(0 == strcasecmp($id, $elemid))
               return $elem;
         }
      }
      
      return null;
   }


   public function getElementByName($type, $name)
   {
      foreach($this->elements as &$elem)
      {
         if(($elem->type == $type) &&
            (0 == strcasecmp($name, $elem->name)))
            return $elem;
      }
      
      return null;
   }

   public function getElementsByType($type)
   {
      $retval = [];
      foreach($this->elements as &$elem)
      {
         if($elem->type == $type)
            $retval[] = $elem;
      }
      
      return $retval;
   }
}