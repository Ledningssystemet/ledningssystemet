<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DataCategory;
use Illuminate\Support\Facades\DB;
use App\Exceptions\SoftException;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Customer;
use App\Models\Process;


class DataProcessingTableController extends Controller
{
    public static function getControllerItems()
    {
       // Authorize action
       if(!auth()->user()->hasAnyPermission(['processingregister.edit', 'processingregister.read']))
          abort(401);
      $retval = [];
      
      $departments = \App\Models\Department::when(request()->has('department_id') && (0 < intval(request()->input('department_id'))), function (Builder $query) {
         if(0 < intval(request()->input('department_id', -1)))
            $query->where('id', request()->input('department_id'));
      })->pluck('id');
      
      foreach(Process::whereIn('department_id', $departments)->orderBy('name')->get() as $process)
      {
         $activities = [];
         $infotypes = [];
         $assets = [];
         $processors = [];
         $recipientcategories = [];
         $subjectcategories = [];
         $datacategories = [];
         $processhaspii = false;
         
         // Extract process activity data
         foreach($process->int_process_activities()->orderBy('ordinal')->get() as $processactivity)
         {
            // Extract information type information
            $activityhaspiiinfo = false;
            foreach($processactivity->int_information_types as $informationtype)
            {
               $ithaspii = false;
               foreach($informationtype->int_subject_categories as $obj)
               {
                  $ithaspii = true;
                  $haspiiinfo = true;
                  $processhaspii = true;
                  $activityhaspiiinfo = true;
                  $subjectcategories[$obj->id] = array('id' => $obj->id, 'name' => $obj->name, 'description' => $obj->description);
               }
                  
               foreach($informationtype->int_recipient_categories as $obj)
               {
                  $ithaspii = true;
                  $haspiiinfo = true;
                  $processhaspii = true;
                  $activityhaspiiinfo = true;
                  $recipientcategories[$obj->id] = array('id' => $obj->id, 'name' => $obj->name, 'description' => $obj->description);
               }
               
               foreach($informationtype->int_data_categories as $obj)
               {
                  $ithaspii = true;
                  $haspiiinfo = true;
                  $processhaspii = true;
                  $activityhaspiiinfo = true;
                  $datacategories[$obj->id] = array('id' => $obj->id, 'name' => $obj->name, 'description' => $obj->description, 'sensitive' => $obj->sensitive);
               }
               
               if($ithaspii)
               {
                  $infotypes[$informationtype->id] = array('id' => $informationtype->id, 'name' => $informationtype->name, 'description' => $informationtype->description, 'retention' => $informationtype->retention, 'piidescription' => $informationtype->piidescription);
                  foreach($informationtype->int_assets as $asset)
                  {
                     if($asset->int_processes()->where('process_id', $process->id)->exists())
                     {
                        $assets[$asset->id] = array('id' => $asset->id, 'name' => $asset->name, 'description' => $asset->description, 'supplier_id' => (null == $asset->int_supplier) ? null : $asset->int_supplier->id, 'supplier_name' => (null == $asset->int_supplier) ? null : $asset->int_supplier->name);
                     }
                  }
               }
            }
            
            if($activityhaspiiinfo)
            {
               $activities[$processactivity->id] = array('id' => $processactivity->id, 'name' => $processactivity->name, 'description' => $processactivity->description);
               
               // Extract suppliers related to outsourcing
               foreach($processactivity->int_suppliers()->where('dataprocessor', true)->orderBy('name')->get() as $supplier)
               {
                  $processors[$supplier->id] = array('id' => $supplier->id, 'name' => $supplier->name, 'description' => $supplier->description);
               }
            }
         }
         
         // Extract information about subprocessors regarding assets
         foreach($assets as $asset)
         {
            $supplier = (null != $asset['supplier_id']) ? \App\Models\Supplier::findOrFail($asset['supplier_id']) : null;
            if((null != $supplier) && $supplier->dataprocessor)
               $processors[$supplier->id] =  array('id' => $supplier->id, 'name' => $supplier->name, 'description' => $supplier->description);
         }

         
         if($processhaspii)
         {
            $processinfo = array(
               'id' => $process->id,
               'name' => $process->name,
               'description' => $process->description,
               'legalbasisdescription' => $process->legalbasisdescription,
               'thirdcountrytransferdescription' => $process->thirdcountrytransferdescription,
               'thirdcountrytransferprotectiondescription' => $process->thirdcountrytransferprotectiondescription,
               'securitymeasuredescription' => $process->securitymeasuredescription
            );

            $status = ['icon' => 'check', 'level' => 'info', 'text' => ''];
            if(!$process->int_legal_basises()->where('sensitive', true)->exists())
            {
               foreach($process->int_information_types as $informationtype)
               {
                  foreach($informationtype->int_data_categories as $datacategory)
                  {
                     if($datacategory->sensitive)
                     {
                        $status = ['icon' => 'warning', 'level' => 'warning', 'text' => __('Process handles sensitive pii but does not have a legal basis that allows sensitive data processing')];
                        break 2;
                     }
                  }
               }
            }
            else if(0 == $process->int_legal_basises()->count())
            {
               $status = ['icon' => 'warning', 'level' => 'danger', 'text' => __('Process handles pii but does not have a legal basis defined')];
            }
            $retval[] = array(
               'id' => $process->id,
               'department' => array('id' => $process->int_department->id, 'name' => $process->int_department->name),
               'process' => $processinfo,
               'activities' => $activities,
               'informationtypes' => $infotypes,
               'assets' => $assets,
               'legalbasises' => $process->int_legal_basises,
               'processors' => $processors,
               'recipientcategories' => $recipientcategories,
               'subjectcategories' => $subjectcategories,
               'datacategories' => $datacategories,
               'status' => $status,
            );
               
               
            
         }
      }

      return response()->json($retval);
    }

    public static function getProcessorItems($byCustomer = false)
    {
       // Authorize action
       if(!auth()->user()->hasAnyPermission(['processingregister.edit', 'processingregister.read']))
          abort(401);
       
      $retval = [];
      
      if($byCustomer)
      {
         foreach(Customer::orderBy('name')->get() as $customer)
         {
            // Skip if customer does not have any processing processes
            if(!$customer
               ->int_processes()
               ->when(0 < request()->input('process_id', 0), function ($query){$query->where('processes.id', request()->input('process_id', 0));})
               ->count())
               continue;
            
            $customer->setAppends([]);
            
            $processes = $customer
                  ->int_processes()
                  ->when(0 < request()->input('process_id', 0), function ($query){
                        $query->where('processes.id', request()->input('process_id', 0));
                     })
                  ->where('dataprocessor', 1)
                  ->orderBy('name')
                  ->select(['processes.name', 'processes.description' , 'processes.data_processor_processing_activities' , 'processes.thirdcountrytransferdescription' , 'processes.thirdcountrytransferprotectiondescription' , 'processes.securitymeasuredescription'])
                  ->get();
            
            $processes->each(function($obj){
               $obj = $obj->setAppends([]);
            });
            
            $retval[] = array_merge($customer->toArray(), array(
               'processes' => $processes,
               )
            );
         }
      }
      else
      {
         foreach(Process::where('dataprocessor', 1)->orderBy('name')->get() as $process)
         {
            $process->setAppends([]);
            
            $customers = $process
               ->int_customers()
               ->when(0 < request()->input('customer_id', 0), function ($query){
                     $query->where('customers.id', request()->input('customer_id', 0));
                  })
               ->orderBy('name')
               ->get();

            $customers->each(function($obj){
               $obj = $obj->setAppends([]);
            });
            
            $retval[] = array_merge($process->toArray(), array(
               'customers' => $customers,
               )
            );
         }
      }
       

      return response()->json($retval);
    }
}
