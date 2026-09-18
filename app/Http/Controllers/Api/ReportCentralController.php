<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ReportCentralController extends Controller
{
   
   /**
    * Get array data by dot notation
    */
   public static function GetDataObject(&$data, $name, $isInArray = false)
   {
      // Split into path
      $pathparts = explode('.', $name);
      
      // Skip stupid stuff
      if(!preg_match('/^[\w_-]+$/', $pathparts[0]))
         return null;
      
      // Check if item exists
      if(!array_key_exists($pathparts[0], $data))
         return null;
      
      // Is this the sought for item?
      if(1 == count($pathparts))
      {
         // If the item is an array, return null
         if(is_array($data[$pathparts[0]]))
            return null;
         
         // Else return the element
         return $data[$pathparts[0]];
      }
      
      // Check if item is an array
      if(is_array($data[$pathparts[0]]))
      {
         // If already in array, then abort
         if($isInArray)
            return null;
         
         $retval = [];
         foreach($data[$pathparts[0]] as $obj)
            $retval[] = ReportCentralController::GetDataObject($obj, implode(array_slice($pathparts, 1)), true);

         return $retval;
         
      }
      else
         return null;
   }
   
   /**
    * Create Excel file export by template name
    */
   public static function GenerateExcel($templatename, $outputname, $data)
   {
      // Throw 404 if template does not exist
      if(!file_exists(__DIR__.'/../../../../resources/templates/'.$templatename.'.xlsx'))
         abort(404, 'Could not find suitable template');
      
      // Load file
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__.'/../../../../resources/templates/'.$templatename.'.xlsx');
      
      // Work through all worksheets
      for($sheetIndex = 0; $sheetIndex < $spreadsheet->getSheetCount(); $sheetIndex++)
      {
         // Load worksheet
         $activeWorksheet = $spreadsheet->getSheet($sheetIndex);
         
         // Get boundaries
         $numRows = $activeWorksheet->getHighestDataRow();
         $numCols = $activeWorksheet->getHighestDataColumn();
         $numCols = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($numCols);
         
         // Loop through all rows
         for($row = 1; $row <= $numRows; $row++)
         {
            // Loop thrugh all columns
            for($col = 1; $col <= $numCols; $col++)
            {
               $cellvalue = $activeWorksheet->getCell([$col, $row])->getValue();
               
               // Check if cell has a {}-parameter
               if(("" != $cellvalue) &&
                  (false !== strpos($cellvalue, '{')) &&
                  (false !== strpos($cellvalue, '}')))
               {
                  $matchcount = preg_match_all('/{(.*?)}/', $cellvalue, $matches);

                  if($matchcount)
                  {
                     for($i = 0; $i < $matchcount; $i++)
                     {
                        $matchname = $matches[1][$i];
                        $dataobj = ReportCentralController::GetDataObject($data, $matchname);
                        
                        if(null == $dataobj)
                           $dataobj = "";
                        
                        
                        // If data object is not an array, wrap it into one
                        if(!is_array($dataobj))
                           $dataobj = [$dataobj];
                        
                        // Perform replacement
                        $newRowIndex = $row;
                        foreach($dataobj as $repobj)
                        {
                           // Check if there is already a value, otherwise copy the original cell value
                           if("" == trim($activeWorksheet->getCell([$col, $newRowIndex])->getValue() ?? ""))
                              $activeWorksheet->setCellValue([$col, $newRowIndex], $cellvalue);
                           
                           // Perform replacement
                           $activeWorksheet->setCellValue([$col, $newRowIndex], str_replace('{'.$matchname.'}', $repobj, ($activeWorksheet->getCell([$col, $newRowIndex])->getValue() ?? "")));
                           
                           $newRowIndex++;
                        }
                     }
                  }
               }
            }
            
            $numRows = $activeWorksheet->getHighestDataRow();
         }
         
         // Row height adjustment
         $highestRow = $activeWorksheet->getHighestRow();
         for ($row = 1; $row <= $highestRow; $row++) {
            $activeWorksheet->getRowDimension($row)->setRowHeight(-1);
         }
      }

      // Output
      $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
      
      // Output to browser
      header( "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" );
      header( 'Content-Disposition: attachment; filename="'.$outputname.'.xlsx"');
      $writer->save( "php://output" );
      exit;       
         
   }
   
   
    /**
     * Generate statement of applicability
     */
    public static function StatementOfApplicability($id)
    {
       Gate::authorize('index', new \App\Models\RequirementSource);

       $obj = \App\Models\RequirementSource::findOrFail($id);

       $data = array_merge([
          'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
          'date' => date("Y-m-d"),
          'time' => date("H:i:s"),
          'requirements' => [],
          'controls' => [],
       ], $obj->getAttributes());

       $applicablecontrols = [];

       // Create requirements object
       foreach ($obj->int_requirements()->orderBy('ordinal')->get() as $req) {
          $reqobj = $req->toArray();
          $reqobj['referencename'] = $req->reference.' '.$req->name;
          $reqobj['controls'] = '';
          $reqobj['pendingactions'] = '';
          $reqobj['applicable'] = $req->applicable ? __("Yes") : __("No");
          $reqobj['applicability'] = "";
          $reqobj['risks'] = "";

          if ($req->applicable) {
             foreach ($req->int_controls()->whereNull('controls.not_applicable_at')->get() as $control) {

                if(!array_key_exists($control->id, $applicablecontrols)) {
                   $pendingactions = "";
                   $implementedactions = "";

                   foreach($control->int_control_actions()->whereNull('finished_at')->orderBy('due')->get() as $pendingaction)
                      $pendingactions .= (("" != $pendingactions) ? "\r\n" : "") . $pendingaction->name . " (" . __("due") . " " . $pendingaction->due . ")";

                   foreach($control->int_control_actions()->whereNotNull('finished_at')->orderBy('due')->get() as $implementedaction)
                      $implementedactions .= (("" != $implementedactions) ? "\r\n" : "") . $implementedaction->name;

                   $applicablecontrols[$control->id] = [
                      'id' => $control->id,
                      'name' => $control->name,
                      'description' => $control->description,
                      'statusdescription' => $control->statusdescription,
                      'pendingactions' => $pendingactions,
                      'implementedactions' => $implementedactions,
                      'requirements' => '',
                   ];
                }

                $applicablecontrols[$control->id]['requirements'] .= (("" != $applicablecontrols[$control->id]['requirements']) ? "\r\n" : "") . $req->name;

                $reqobj['controls'] .= (("" != $reqobj['controls']) ? "\r\n" : "") . $control->name;

                if ($control->int_risks()->count())
                   $reqobj['applicability'] = __("This requirement have controls bound to it that are necessary to mitigate identified risks");

                foreach ($control->int_risks()->whereNull('replacedby_id')->get() as $risk)
                   $reqobj['risks'] .= (("" != $reqobj['risks']) ? ", " : "") . "RISK-" . $risk->id;

                foreach ($control->int_control_actions()->whereNull('finished_at')->get() as $ca)
                   $reqobj['pendingactions'] .= (("" != $reqobj['pendingactions']) ? "\r\n" : "") . $ca->name . " (" . __("control") . " " . $control->name . ", " . __("due") . " " . $ca->due . ")";

             }

             if ("" == $reqobj['applicability']) {
                $reqobj['applicability'] = __("This requirement is applicable for our organization, see governance information for details");
             }
          } else {
             $reqobj['applicability'] = __("This requirement is not applicable for our business");
          }
          $data['requirements'][] = $reqobj;
       }

       // Sort controls
       usort($applicablecontrols, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

       $data['controls'] = $applicablecontrols;
       ReportCentralController::GenerateExcel('SoA_' . config('ledningssystemet.locale', 'en'), 'Statement of Applicability', $data);
    }
    /**
     * Generate information types list
     */
    public static function InformationTypes($id)
    {
      Gate::authorize('index', new \App\Models\InformationType);
      
      $data = [
         'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
         'date' => date("Y-m-d"),
         'time' => date("H:i:s"),
         'informationtypes' => [],
      ];
      
      // Create data object
      foreach(\App\Models\InformationType::orderBy('name')->get() as $obj)
      {
         $objdata = $obj->toArray();
         
         $procs = [];
         foreach($obj->int_processes() as $process)
            $procs[$process->id] = $process->name;
         sort($procs);
         
         $assets = $obj->int_assets()->distinct()->orderBy('name')->pluck('name')->toArray();
         sort($assets);
         
         $objdata['processes'] = implode("\r\n", $procs);
         $objdata['assets'] = implode("\r\n", $assets);
         $objdata['retention'] = $objdata['retention'] ? $objdata['retention'].' '.__("months") : "";
         $objdata['responsible'] = $obj->responsible_user_id ? \App\Models\User::findOrFail($obj->responsible_user_id)->name : "";
         $objdata['confidentiality'] = $obj->confidentiality_class_id ?  \App\Models\ConfidentialityClass::findOrFail($obj->confidentiality_class_id)->name : "";
         $objdata['integrity'] = $obj->integrity_class_id ?  \App\Models\IntegrityClass::findOrFail($obj->integrity_class_id)->name : "";
         $objdata['availability'] = $obj->availability_class_id ? \App\Models\AvailabilityClass::findOrFail($obj->availability_class_id)->name : "";

         $data['informationtypes'][] = $objdata;
      }
      
      ReportCentralController::GenerateExcel('InformationTypes_'.config('ledningssystemet.locale', 'en'), 'Information types', $data);
    }


    /**
     * Generate assets list
     */
    public static function Assets($id)
    {
      Gate::authorize('index', new \App\Models\Asset);
      
      $data = [
         'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
         'date' => date("Y-m-d"),
         'time' => date("H:i:s"),
         'assets' => [],
      ];
      
      // Create data object
      foreach(\App\Models\Asset::orderBy('name')->get() as $obj)
      {
         $objdata = $obj->toArray();
         
         $procs = [];
         foreach($obj->int_information_types as $it)
         {
            foreach($it->int_process_activities as $pa)
               $procs[$pa->int_process->id] = $pa->int_process->name;
         }
         sort($procs);
         
         $clevel = $obj->int_confidentialityclass();
         $ilevel = $obj->int_integrityclass();
         $alevel = $obj->int_availabilityclass();
         
		 $objdata['processes'] = implode("\r\n", $procs);
         
         $objdata['informationtypes'] = implode("\r\n", $obj->int_information_types()->distinct()->orderBy('name')->pluck('name')->toArray());
         $objdata['responsible'] = $obj->responsible_user_id ? \App\Models\User::findOrFail($obj->responsible_user_id)->name : "";
         $objdata['confidentiality'] = $clevel ?  $clevel->name : "";
         $objdata['integrity'] = $ilevel ?  $ilevel->name : "";
         $objdata['availability'] = $alevel ?  $alevel->name : "";
         $objdata['mtd'] = $objdata['mtd'] ? $objdata['mtd'].' '.__("hours") : "";
         $objdata['rpo'] = $objdata['rpo'] ? $objdata['rpo'].' '.__("hours") : "";
         $objdata['site'] = (null != $obj->site_id) ? $obj->int_site->name : "";
         
         if(!config('ledningssystemet.disable_supplier'))
            $objdata['supplier'] = $obj->int_supplier ?  $obj->int_supplier->name : "";

         

         $data['assets'][] = $objdata;
      }
      
      ReportCentralController::GenerateExcel('Assets_'.config('ledningssystemet.locale', 'en'), 'Assets', $data);
    }



    /**
     * Generate suppliers list
     */
    public static function Suppliers($id)
    {
      Gate::authorize('view', new \App\Models\Supplier);
      
      $data = [
         'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
         'date' => date("Y-m-d"),
         'time' => date("H:i:s"),
         'suppliers' => [],
      ];
      
      // Create data object
      foreach(\App\Models\Supplier::orderBy('name')->get() as $obj)
      {
         $objdata = $obj->toArray();
         
         $objdata['processactivities'] = "";
         
         foreach($obj->int_process_activities as $pa)
            $objdata['processactivities'] .= (("" != $objdata['processactivities']) ? "\r\n" : "").$pa->name." (".__("Process")." ".$pa->int_process->name.")";
         
         $objdata['assets'] = "";
         
         foreach($obj->int_assets()->orderBy('name')->get() as $asset)
            $objdata['assets'] .= (("" != $objdata['assets']) ? "\r\n" : "").$asset->name;

         $objdata['dataprocessor'] = $obj->dataprocessor ? __("Yes") : __("No");
         $objdata['responsible'] = $obj->responsible_user_id ? \App\Models\User::findOrFail($obj->responsible_user_id)->name : "";


         $data['suppliers'][] = $objdata;
      }

      
      ReportCentralController::GenerateExcel('Suppliers_'.config('ledningssystemet.locale', 'en'), 'Suppliers', $data);
    }    
    
   /**
    * Compliance evaluation report
    */
   public static function ComplianceEvaluation($id)
   {
      $obj = \App\Models\ComplianceEvaluation::findOrFail($id);
      Gate::authorize('view', $obj);
      
      $phpWord = new \PhpOffice\PhpWord\TemplateProcessor(__DIR__.'/../../../../resources/templates/ComplianceEvaluation_'.config('ledningssystemet.locale', 'en').'.docx');
      
      // Replace all attributes
      $attrs = $obj->getOriginal();
      $attrs['summary'] = str_replace("\n", '</w:t><w:br/><w:t>', $attrs['summary']);
      $attrs['description'] = str_replace("\n", '</w:t><w:br/><w:t>', $attrs['description']);
      $attrs['participants'] = str_replace("\n", '</w:t><w:br/><w:t>', $attrs['participants']);
      $attrs['finished'] = (null == $attrs['finished']) ? __("ongoing") : date("Y-m-d", strtotime($attrs['finished']));
      
      // Get all requirements
      $checked = [];
      $clean = [];
      $notapplicable = [];
      $withfindings = [];
      $withnotes = [];
      
      // Non-conformities and observations
      $observations = [];
      $nonconformities = [];
      
      // Append scope info
      $attrs['scope'] = "";
      foreach($obj->int_requirement_sources as $reqsource)
         $attrs['scope'] .= (("" != $attrs['scope']) ? "</w:t><w:br/><w:t>" : '').$reqsource->reference.' '.$reqsource->name;

      // Append stats info
      $stats = $obj->getstats();
      foreach(array_keys($stats) as $statkey)
         $attrs['stat.'.$statkey] = strval($stats[$statkey])." ";
      
      // Append findings info
      foreach($obj->int_compliance_evaluation_requirements as $req)
      {
         $reqobj = $req->getOriginal();
         $reqobj['description'] = str_replace("\n", '</w:t><w:br/><w:t>', $reqobj['description']);
         $reqobj['governance'] = str_replace("\n", '</w:t><w:br/><w:t>', $reqobj['governance']);
         $reqobj['note'] = str_replace("\n", '</w:t><w:br/><w:t>', $reqobj['note']);
         $reqobj['req-source'] = $req->int_requirement->int_requirement_source->reference. ' '.$req->int_requirement->int_requirement_source->name;
         $reqobj['req-reference'] = $req->reference;
         $reqobj['req-name'] = $req->name;
         $nccount = 0;
         $observationcount = 0;
         
         foreach($req->int_compliance_evaluation_requirement_findings as $findingobj)
         {
            $findingobjattrs = $findingobj->getOriginal();
            $findingobjattrs['finding-name'] = $findingobjattrs['name'];
            $findingobjattrs['finding-description'] = str_replace("\n", '</w:t><w:br/><w:t>', $findingobjattrs['description']);
            
            foreach(array_keys($reqobj) as $reqobjkey)
               $findingobjattrs['req-'.$reqobjkey] = $reqobj[$reqobjkey];
               
            foreach(array_keys($attrs) as $attrskey)
               $findingobjattrs['reqsource-'.$attrskey] = $attrs[$attrskey];
               
            if($findingobj->isnc)
            {
               $nccount++;
               $nonconformities[] = $findingobjattrs;
            }
            else
            {
               $observationcount++;
               $observations[] = $findingobjattrs;
            }
         }
         
         if($req->evaluated && !$req->applicable)
            $notapplicable[] = $reqobj;
         else
            $checked[] = $reqobj;
         
         if($req->evaluated && $req->applicable)
         {
            if(0 == ($nccount + $observationcount))
               $clean[] = $reqobj;
            else
               $withfindings[] = $reqobj;
            
            if("" != $reqobj['note'])
               $withnotes[] = $reqobj;
         }
      }

      // Append requirement source notes
      $reqsourceswithnotes = [];

      foreach($obj->int_compliance_evaluation_requirement_sources as $reqsource)
      {
         $reqsourceobject = $reqsource->getOriginal();
         $reqsourceobject['note'] = str_replace("\n", '</w:t><w:br/><w:t>', $reqsourceobject['note']);
         $reqsourceobject['reqsource'] = $reqsource->int_requirement_source->reference. ' '.$reqsource->int_requirement_source->name;

         if($reqsource->note && ($reqsource->note != ""))
            $reqsourceswithnotes[] = $reqsourceobject;
      }

      // Replace compliance evaluation objects
      $phpWord->setValues($attrs);
      
      // Clone blocks for each element
      $phpWord->cloneBlock('requirements.notapplicable', 0, true, false, $notapplicable);
      $phpWord->cloneBlock('requirements.checked', 0, true, false, $checked);
      $phpWord->cloneBlock('requirements.clean', 0, true, false, $clean);
      $phpWord->cloneBlock('requirements.withfindings', 0, true, false, $withfindings);
      $phpWord->cloneBlock('requirements.withnotes', 0, true, false, $withnotes);
      $phpWord->cloneBlock('observations', 0, true, false, $observations);
      $phpWord->cloneBlock('nonconformities', 0, true, false, $nonconformities);
      $phpWord->cloneBlock('requirementsources.withnotes', 0, true, false, $reqsourceswithnotes);
      
      // Output to browser
      header( "Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document" );
      header( 'Content-Disposition: attachment; filename=ComplianceReport.docx');
      $phpWord->saveAs( "php://output" );
      exit;
   } 

    /**
     * Generate riskregister
     */
    public static function Risks($id)
    {
       if(!auth()->user()->hasAnyPermission(['riskadministrator.edit']))
          abort(403);
      
      $data = [
         'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
         'date' => date("Y-m-d"),
         'time' => date("H:i:s"),
         'risks' => [],
      ];
      
      // Create data object
      foreach(\App\Models\Risk::whereNull('replacedby_id')->whereNull('risk_project_id')->orderBy('id', 'desc')->get() as $obj)
      {
         $objdata = $obj->toArray();
         
         $objdata['controls'] = "";
         
         foreach($obj->int_controls()->whereNull('controls.not_applicable_at')->get() as $control)
            $objdata['controls'] .= (("" != $objdata['controls']) ? "\r\n" : "").$control->name;
         
         $objdata['riskowner'] = $obj->int_riskowner ? $obj->int_riskowner->name : "";
         $objdata['probability'] = $obj->int_probability? $obj->int_probability->name : "";
         $objdata['consequence'] = $obj->int_consequence ? $obj->int_consequence->name : "";
         $objdata['risklevel'] = $obj->int_risklevel() ? $obj->int_risklevel()->name : "";
         $objdata['department'] = $obj->int_department ? $obj->int_department->name : "";
         $objdata['context'] = '';
         if($obj->context_type && $obj->context_id)
         {
            $contextobj = $obj->context_type::find($obj->context_id);
            if(null != $contextobj)
               $objdata['context'] = $contextobj->name;
         }
         $objdata['assessmentstatus'] = (null != $obj->assessed_at) ? __("Assessed") : __("Pending assessment");
         $objdata['updated'] = date("Y-m-d", strtotime($obj->updated_at));
         
        $objdata['controlactions'] = "";
         
         foreach($obj->int_control_actions as $controlaction)
            $objdata['controlactions'] .= (("" != $objdata['controlactions']) ? "\r\n" : "").$controlaction->name.' ('.((null == $controlaction->finished_at) ? __("Pending") : __("Finished")).')';
         


         $data['risks'][] = $objdata;
      }

      
      ReportCentralController::GenerateExcel('Risks_'.config('ledningssystemet.locale', 'en'), 'Risks', $data);
    }    



    /**
     * Generate controls list
     */
    public static function Controls($id)
    {
      Gate::authorize('index', new \App\Models\Control);
      
      $data = [
         'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
         'date' => date("Y-m-d"),
         'time' => date("H:i:s"),
         'controls' => [],
      ];
      
      // Create data object
      foreach(\App\Models\Control::whereNull('not_applicable_at')->orderBy('name')->get() as $obj)
      {
         $objdata = $obj->toArray();
         
         $objdata['requirements'] = "";
         
         foreach($obj->int_requirements()->orderBy('requirement_source_id')->orderBy('ordinal')->get() as $requirement)
            $objdata['requirements'] .= (("" != $objdata['requirements']) ? "\r\n" : "").$requirement->name;

         $objdata['risks'] = "";
         
         foreach($obj->int_risks()->whereNull('replacedby_id')->get() as $risk)
            $objdata['risks'] .= (("" != $objdata['risks']) ? "\r\n" : "").__("RISK")."-".$risk->id." ".$risk->name_pretty;
            
         $objdata['pendingactions'] = "";
         
         foreach($obj->int_control_actions()->whereNull('finished_at')->get() as $action)
            $objdata['pendingactions'] .= (("" != $objdata['pendingactions']) ? "\r\n" : "").$action->name;
            
         $data['controls'][] = $objdata;
      }
      
      ReportCentralController::GenerateExcel('Controls_'.config('ledningssystemet.locale', 'en'), 'Controls', $data);
    }

   /**
    * Generate document management plan
    */
   public static function DocumentManagementPlan($id)
   {
      if(!auth()->user()->hasAnyPermission(['docmgmtplan.read', 'docmgmtplan.edit']))
         abort(403);

      $data = [
         'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
         'date' => date("Y-m-d"),
         'time' => date("H:i:s"),
         'informationtypes' => [],
      ];

      // Create data object
      foreach(\App\Models\InformationType::orderBy('name')->get() as $obj)
      {
         $objdata = $obj->toArray();

         $procs = [];
         foreach($obj->int_processes() as $process)
            $procs[$process->id] = $process->name;
         sort($procs);

         $assets = $obj->int_assets()->distinct()->orderBy('name')->pluck('name')->toArray();
         sort($assets);

         $objdata['processes'] = implode("\r\n", $procs);
         $objdata['assets'] = implode("\r\n", $assets);
         $objdata['confidential'] = $obj->confidentiality_ground_id ? __("Yes") : __("No");
         $objdata['diary'] = $obj->diary_id ? $obj->int_diary->name : __("Not recorded");
         $objdata['retention'] = $objdata['retention'] ? $objdata['retention'].' '.__("months") : "";

         if($obj->confidentiality_ground_id)
         {
            $objdata['archivingdescription'] .= ("" != $objdata['archivingdescription']) ? "\r\n\r\n" : "";
            $objdata['archivingdescription'] .= __("This information type is classified as confidential with reference to")." ".$obj->int_confidentiality_ground->name;
         }

         $data['informationtypes'][] = $objdata;
      }

      ReportCentralController::GenerateExcel('DocumentManagementPlan_'.config('ledningssystemet.locale', 'en'), 'InformationHandlingPlan', $data);
   }


   /**
    * Generate risk project
    */
   public static function RiskProject($id)
   {
      $riskproject = \App\Models\RiskProject::findOrFail($id);
      if(!auth()->user()->can('view', $riskproject))
         abort(403);

      $data = [
         'company' => [['name' => config('ledningssystemet.company_name', ''), 'uid' => config('ledningssystemet.company_uid', '')]],
         'date' => date("Y-m-d"),
         'time' => date("H:i:s"),
         'risks' => [],
      ];

      // Create data object
      foreach($riskproject->int_risks()->whereNull('replacedby_id')->orderBy('id', 'desc')->get() as $obj)
      {
         $objdata = $obj->toArray();

         $objdata['controls'] = "";

         foreach($obj->int_controls()->whereNull('controls.not_applicable_at')->get() as $control)
            $objdata['controls'] .= (("" != $objdata['controls']) ? "\r\n" : "").$control->name;

         $objdata['riskowner'] = $obj->int_riskowner ? $obj->int_riskowner->name : "";
         $objdata['probability'] = $obj->int_probability? $obj->int_probability->name : "";
         $objdata['consequence'] = $obj->int_consequence ? $obj->int_consequence->name : "";
         $objdata['risklevel'] = $obj->int_risklevel() ? $obj->int_risklevel()->name : "";
         $objdata['department'] = $obj->int_department ? $obj->int_department->name : "";
         $objdata['context'] = '';
         if($obj->context_type && $obj->context_id)
         {
            $contextobj = $obj->context_type::find($obj->context_id);
            if(null != $contextobj)
               $objdata['context'] = $contextobj->name;
         }
         $objdata['assessmentstatus'] = (null != $obj->assessed_at) ? __("Assessed") : __("Pending assessment");
         $objdata['updated'] = date("Y-m-d", strtotime($obj->updated_at));

         $objdata['controlactions'] = "";

         foreach($obj->int_control_actions as $controlaction)
            $objdata['controlactions'] .= (("" != $objdata['controlactions']) ? "\r\n" : "").$controlaction->name.' ('.((null == $controlaction->finished_at) ? __("Pending") : __("Finished")).')';



         $data['risks'][] = $objdata;
      }

      ReportCentralController::GenerateExcel('Risks_'.config('ledningssystemet.locale', 'en'), 'RiskProject', $data);
   }

}
