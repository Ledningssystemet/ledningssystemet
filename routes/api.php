<?php

use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\AssessmentSettingsController;
use App\Http\Controllers\Api\DataProcessingTableController;
use App\Http\Controllers\Api\DocumentManagementTableController;
use App\Http\Controllers\Api\LegacyTableController;
use App\Http\Controllers\Api\ReportCentralController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\StatusController;
use App\Models\Department;
use App\Models\LibraryDocument;
use App\Models\RiskLevel;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// API v1 routes
Route::prefix('v1')->group(function () {

    /* Status controller routes */
    Route::get('/getstatus', function () {
        return StatusController::getStatus();
    });

    if (! config('ledningssystemet.disable_gdpr')) {
        /* Data processing register routes */
        Route::get('/processDataProcessingRegister/controller', function () {
            return DataProcessingTableController::getControllerItems();
        });
        Route::get('/processDataProcessingRegister/processorprocess', function () {
            return DataProcessingTableController::getProcessorItems(false);
        });
        Route::get('/processDataProcessingRegister/processorcustomer', function () {
            return DataProcessingTableController::getProcessorItems(true);
        });
    }

    if (! config('ledningssystemet.disable_archival')) {
        /* Data processing register routes */
        Route::get('/documentManagementPlan', function () {
            return DocumentManagementTableController::getItems();
        });
    }

    if (! config('ledningssystemet.disable_processes')) {
        // Download
        Route::get('/LibraryDocument/{document}/download', function (LibraryDocument $document) {
            if ($document->contentlength == 0) {
                abort(404);
            }
            header('Content-Type: '.(($document->contenttype == 'ledningssystemet/document') ? 'application/pdf' : $document->contenttype));
            header('Content-Disposition: attachment; filename="'.$document->filename.'"');
            header('Content-Length: '.$document->contentlength);
            exit($document->filecontent);
        })->where('document', '\d+')->withoutMiddleware(['jsononly']);
    }

    /* Report central routes */
    Route::get('/ReportCentral/{reporttype}/{id}', function ($reporttype, $objid) {
        if (method_exists((new ReportCentralController), $reporttype)) {
            return ReportCentralController::$reporttype($objid);
        } else {
            abort(404);
        }
    })->where('reporttype', '\w+')->where('id', '\d+')->withoutMiddleware(['jsononly']);

    /* Risk level mappings */
    Route::get('/RiskAssessmentSettings/riskmappings', function () {
        if (Auth::user()->cannot('index', RiskLevel::class)) {
            abort(403);
        }

        return (new AssessmentSettingsController)->getriskmappings();
    });

    // Save assessment levels
    Route::post('/RiskAssessmentSettings', function () {
        if (Auth::user()->cannot('update', RiskLevel::class)) {
            abort(403);
        }

        return (new AssessmentSettingsController)->saverisklevels();
    });
});

// API v1 item routes
Route::prefix('v1/items')->group(function () {
    // Index function
    Route::get('/{model}', [LegacyTableController::class, 'index'])->where('model', '[a-zA-Z]+')->name('api.v1.items.index');

    // View function
    Route::get('/{model}/{id}', [LegacyTableController::class, 'show'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->name('api.v1.items.view');

    // Create function
    Route::post('/{model}', [LegacyTableController::class, 'create'])->where('model', '[a-zA-Z]+')->name('api.v1.items.create');

    // Update function
    Route::patch('/{model}/{id}', [LegacyTableController::class, 'update'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->name('api.v1.items.update');

    // Delete function
    Route::delete('/{model}/{id}', [LegacyTableController::class, 'delete'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->name('api.v1.items.delete');

    // Custom action call
    Route::any('/{model}/{id}/{action}', [LegacyTableController::class, 'customAction'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->where('action', '[a-zA-Z]+')->withoutMiddleware(['jsononly']);

    // Get object tags
    Route::get('/getTags/{model}/{id}', function ($model, $id) {
        return (new TagController)->getTags($model, $id);
    })->where('model', '[a-zA-Z]+')->where('id', '[A-Za-z_\d]+');

    // Get object tags
    Route::get('/getAllTags/{model}/{id}', function ($model, $id) {
        return (new TagController)->getAllTags($model, $id);
    })->where('model', '[a-zA-Z]+')->where('id', '[A-Za-z_\d]+');

    // Set object tags
    Route::post('/setTags/{model}/{id}', function ($model, $id) {
        return (new TagController)->setTags($model, $id);
    })->where('model', '[a-zA-Z]+')->where('id', '[A-Za-z_\d]+');
});

// API v1 item routes
Route::prefix('v1/crud')->group(function () {
    // Index function
    Route::get('/{model}', [TableController::class, 'index'])->where('model', '[a-zA-Z]+')->name('api.v1.crud.index');

    // View function
    Route::get('/{model}/{id}', [TableController::class, 'show'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->name('api.v1.crud.view');

    // Create function
    Route::post('/{model}', [TableController::class, 'create'])->where('model', '[a-zA-Z]+')->name('api.v1.crud.create');

    // Update function
    Route::patch('/{model}/{id}', [TableController::class, 'update'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->name('api.v1.crud.update');

    // Delete function
    Route::delete('/{model}/{id}', [TableController::class, 'delete'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->name('api.v1.crud.delete');

    // Custom action call
    Route::any('/{model}/{id}/{action}', [TableController::class, 'customAction'])->where('model', '[a-zA-Z]+')->where('id', '\d+')->where('action', '[a-zA-Z]+')->withoutMiddleware(['jsononly']);
});

/* List sources for dropdowns */
Route::prefix('v1/listsources')->group(function () {
    Route::get('/departments', function () {
        $retval = [];

        foreach (Department::all() as $department) {
            $retval[] = ['id' => $department->id, 'name' => $department->name];
        }

        return json_encode($retval);
    });
});

Route::prefix('v1/listsources')->group(function () {
    Route::get('/riskowners', function () {
        $retval = [];

        $retval[] = ['id' => null, 'name' => __('None')];

        foreach (User::all() as $user) {
            if ($user->risklevel() != null) {
                $retval[] = ['id' => $user->id, 'name' => $user->name];
            }
        }

        return json_encode($retval);
    });
});

/* AI */
Route::prefix('v1/ai')->group(function () {
    // Stateless chat (one question -> one answer)
    Route::post('/chat/stream', function () {
        return (new AIController)::chatSendStream(null);
    });

    // Get risk
    Route::post('/risk', function () {
        return (new AIController)::getRiskAnalysis();
    });

    // Get risk consequence
    Route::post('/requirementsource', function () {
        return (new AIController)::getRequirementSourceAnalysis();
    });

    // Get finding
    Route::post('/finding', function () {
        return (new AIController)::getFindingAnalysis();
    });

    // Get control
    Route::post('/control', function () {
        return (new AIController)::getControlAnalysis();
    });

    // Get document
    Route::post('/document', function () {
        return (new AIController)::getDocument();
    });

    // Risk identification
    Route::post('/riskidentification', function () {
        if (! class_exists(request()->input('context_type'))) {
            abort(404);
        }

        if (intval(request()->input('context_id', -1)) <= 0) {
            abort(404);
        }

        $contextobj = request()->input('context_type')::findOrFail(request()->input('context_id'));

        return (new AIController)::getRiskSuggestions($contextobj);
    });
});

/* Document controller routes */
Route::prefix('v1/documentcontroller')->group(function () {
    Route::get('/{template}/{model}/{id}', function ($template, $model, $id) {
        // Load requested model
        $classname = 'App\\Models\\'.$model;
        if (! class_exists($classname)) {
            abort(404);
        }

        $modelobj = $classname::findOrFail($id);

        // Authorize user
        if (! Gate::allows('view', $modelobj)) {
            abort(403);
        }

        // Validate that the template exists
        $templatepath = resource_path('templates/xslt/'.$model.'/'.$template.'.xslt');
        if (! file_exists($templatepath)) {
            abort(404);
        }

        $xslt = simplexml_load_file($templatepath);
        if ($xslt === false) {
            abort(404);
        }

        $docxml = DocumentController::generateXml($modelobj, $xslt);
        header('Content-Type: text/xml');
        exit($docxml);

        $pdfdata = DocumentController::generatePdf($docxml, $xslt->asXML(), false);
        if (! $pdfdata) {
            abort(404);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="document.pdf"');
        header('Content-Length: '.strlen($pdfdata));
        exit($pdfdata);

    })
        ->where('model', '[a-zA-Z]+')
        ->where('id', '[A-Za-z_\d]+')
        ->where('template', '[a-zA-Z0.9_]+')
        ->withoutMiddleware(['jsononly']);
});
