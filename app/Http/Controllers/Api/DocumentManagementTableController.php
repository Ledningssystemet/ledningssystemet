<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InformationType;
use Illuminate\Http\Request;
use App\Models\DataCategory;
use Illuminate\Support\Facades\DB;
use App\Exceptions\SoftException;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Customer;
use App\Models\Process;


class DocumentManagementTableController extends Controller
{
   public static function getItems()
   {
      // Authorize action
      if (!auth()->user()->hasAnyPermission(['docmgmtplan.read']))
         abort(401);

      $retval = [];

      foreach (InformationType
                  ::when(intval(request()->input('department_id', 0)), function (Builder $query) {
                     $query->leftJoin('information_type_process_activity', 'information_types.id', '=', 'information_type_process_activity.information_type_id')
                           ->leftJoin('process_activities', 'information_type_process_activity.process_activity_id', '=', 'process_activities.id')
                           ->leftJoin('processes', 'process_activities.process_id', '=', 'processes.id')
                           ->where('processes.department_id', request()->input('department_id'));
                  })
                  ->when(intval(request()->input('confidentiality_ground_id', 0)), function (Builder $query) {
                     $query->where('confidentiality_ground_id', request()->input('confidentiality_ground_id'));
                  })
                  ->when(intval(request()->input('diary_id', 0)), function (Builder $query) {
                     $query->where('diary_id', request()->input('diary_id'));
                  })
                  ->when("" != request()->input('search', ""), function (Builder $query) {
                     $query
                        ->where('information_types.name', 'like', '%'.request()->input('search', '').'%')
                        ->orWhere('information_types.description', 'like', '%'.request()->input('search', '').'%');
                  })
                  ->orderBy('name', 'asc')
                  ->select('information_types.*')
                  ->distinct()
                  ->get() as $informationtype) {

         $departments = [];
		 foreach ($informationtype->int_processes() as $process) {
		    if ($process->department_id)
		 	  $departments[$process->department_id] = ['id' => $process->department_id, 'name' => $process->int_department->name];
		 }
         sort($departments);

         $retval[] = [
            'id' => $informationtype->id,
            'name' => $informationtype->name,
            'responsible' => $informationtype->responsible_user_id ? $informationtype->int_responsible_user->name : null,
            'description' => $informationtype->description,
            'archivingdescription' => $informationtype->archivingdescription,
            'retention' => (null == $informationtype->retention) ? __ ("Not retained") : (($informationtype->retention > 50 * 12) ? __("Retained") : $informationtype->retention.' '.__("months")),
            'departments' => $departments,
            'confidentiality_ground' => $informationtype->confidentiality_ground_id ? $informationtype->int_confidentiality_ground->name : __("None"),
            'diary' => $informationtype->diary_id ? $informationtype->int_diary->name : __("Not recorded"),
            'assets' => $informationtype->assets,
            'sortinginformation' => $informationtype->sortinginformation,
            'archivemedia' => $informationtype->archivemedia,
            'archiveshippingtime' => $informationtype->archiveshippingtime,
            'status' => [
               'icon' => $informationtype->confidentiality_ground_id ? 'shield_lock' : null,
               'level' => $informationtype->confidentiality_ground_id ? 'info' : null,
               'text' => $informationtype->confidentiality_ground_id ? $informationtype->int_confidentiality_ground->name.': '.$informationtype->int_confidentiality_ground->description : null,
            ],
         ];
      }


      return response()->json(['data' => $retval]);
   }
}
