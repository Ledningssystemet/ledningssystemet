<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['namespace' => 'App\Http\Controllers'], function()
{
   // Unauthenticated routes
   Route::group(['middleware' => ['guest']], function()
   {
     /**
      * Authentication-related routes
      */
      // Login
      Route::get('/login', [\App\Http\Controllers\Auth\LoginController::class, 'show'])->name('login');
      Route::post('/login', [\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'store']);
       
      // SSO login
      Route::get('/ssologin', function () {
         return app(\App\Http\Controllers\Auth\SsoController::class)->redirect('azure');
      })->name('ssologin');
      Route::get('/oauthcallback', function (\Illuminate\Http\Request $request) {
         return app(\App\Http\Controllers\Auth\SsoController::class)->callback($request, 'azure');
      })->name('oauthcallback');
      Route::get('/sso/{provider}', 'App\Http\Controllers\Auth\SsoController@redirect')->where('provider', 'azure|google')->name('sso.redirect');
      Route::get('/sso/{provider}/callback', 'App\Http\Controllers\Auth\SsoController@callback')->where('provider', 'azure|google')->name('sso.callback');
       
      // Password reset
      Route::get('/forgot-password', [\Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'create'])->name('password.request');
      Route::post('/forgot-password', [\Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'store'])->name('password.email');
      Route::get('/reset-password/{token}', [\Laravel\Fortify\Http\Controllers\NewPasswordController::class, 'create'])->name('password.reset');
      Route::post('/reset-password', [\Laravel\Fortify\Http\Controllers\NewPasswordController::class, 'store'])->name('password.update');
      if(config('ledningssystemet.mfa_enabled'))
      {
         Route::get('/two-factor-challenge', 'App\Http\Controllers\Auth\TwoFactorChallengeController@create')->name('two-factor.login');
         Route::post('/two-factor-challenge', 'App\Http\Controllers\Auth\TwoFactorChallengeController@store');
      }
      Route::post('/auth/keepalive', function () {
         return response()->json(['ok' => true]);
      })->name('auth.keepalive');
   });

   // Authenticated routes
   Route::group(['middleware' => ['auth', 'authproxy']], function()
   {
     if(config('ledningssystemet.mfa_enabled') && config('ledningssystemet.mfa_enforced'))
     {
        Route::get('/mfa-enforcement', [\App\Http\Controllers\Auth\MfaEnforcementController::class, 'index'])->withoutMiddleware(['authproxy'])->name('mfa.enforcement');
     }

      /**
      * Landing page
      */
      Route::get('/', function() { return view('start'); })->name('home'); // Home
      

      /**
      * Dashboard
      */
      Route::get('/dashboard', function() { return view('dashboard.dashboard'); }); // Dashboard
      
      /**
      * User
      */
      Route::get('/user/preferences', function() { return view('user.preferences'); }); // User preferences
      Route::post('/user/preferences', function() { return view('user.preferences'); }); // User preferences
      
      if(!config('ledningssystemet.disable_staff'))
      {
         Route::get('/user/me', function() { return view('user.me'); }); // User info page
      }
      Route::get('/user/activities', function() { return view('user.activities'); }); // Display activities
      Route::get('/user/controlactions', function() { return view('user.controlactions'); }); // Display actions
      Route::get('/user/documents', function() { return view('user.documents'); }); // Display actions

      /**
      * Inventory routes
      */
      if(!config('ledningssystemet.disable_processes'))
      {
         Route::get('/inventory/processes', function() { return view('inventory.processes'); }); // Display processes
         Route::get('/inventory/processedit/{id}',  function(string $id) { return view('inventory.processedit')->with('process', App\Models\Process::findOrFail($id)); }); // Edit process chart   
         Route::post('/inventory/processedit/{id}/save', 'App\Http\Controllers\ProcessController@save');
         Route::get('/inventory/processedit/{id}/revert', 'App\Http\Controllers\ProcessController@revert');
         Route::get('/inventory/processload/{id}', 'App\Http\Controllers\ProcessController@loadxml');
      }
      
      Route::get('/inventory/customers', function() { return view('inventory.customers'); }); 
      Route::get('/inventory/requirements', function() { return view('inventory.requirements'); }); 
      Route::get('/inventory/informationtypes', function() { return view('inventory.informationtypes'); }); // Display information types
      
      Route::get('/inventory/assets', function() { return view('inventory.assets'); }); // Display assets   
      if(!config('ledningssystemet.disable_supplier'))
      {
         Route::get('/inventory/suppliers', function() { return view('inventory.suppliers'); }); // Display suppliers   
      }         
      
      Route::get('/inventory/controls', function() { return view('inventory.controls'); }); // Display controls
      Route::get('/inventory/sustainabilityaspects', function() { return view('inventory.sustainabilityaspects'); }); // Display sustainability aspect register
      Route::get('/inventory/chemicalregister', function() { return view('inventory.chemicalregister'); }); // Display chemical register
      if(!config('ledningssystemet.disable_gdpr'))
      {
         Route::get('/inventory/dataprocessingregister', function() { return view('inventory.dataprocessingregister'); }); 
      }
      if(!config('ledningssystemet.disable_archival'))
      {
         Route::get('/inventory/docmgmtplan', function() { return view('inventory.docmgmtplan'); });
      }
      Route::get('/inventory/agreements', function() { return view('inventory.agreements'); });
      

      /**
      * Assessment routes
      */
      Route::get('/assessment/riskprojects', function() { return view('assessment.riskprojects'); }); // Display risk projects
      Route::get('/assessment/riskregister', function() { return view('assessment.riskregister'); }); // Display risk register
      Route::get('/assessment/findings', function() { return view('assessment.findings'); }); // Display findings
      Route::get('/assessment/incidents', function() { return view('assessment.incidents'); }); // Display incidents
      Route::get('/assessment/controlactions', function() { return view('assessment.controlactions'); }); // Display actions
      Route::get('/assessment/evaluations', function() { return view('assessment.evaluations'); }); 
      Route::get('/assessment/evaluate/{eval}', function(\App\Models\ComplianceEvaluation $eval) {
         return View::make('assessment.evaluate')->with('evaluation', $eval); 
      });

      if(config('ledningssystemet.exchangeproxy_url'))
         Route::get('/assessment/forms', function() { return view('assessment.forms'); }); // Display forms

      /**
      * Measurement and objective routes
      */
      Route::get('/measure/riskoverview', function() { return view('measure.riskoverview'); }); // Risk overview
      if(!config('ledningssystemet.disable_processes'))
      {
         Route::get('/measure/processperformancemetrics', function() { return view('measure.processperformancemetrics'); }); // Display metrics
         Route::get('/measure/objectives', function() { return view('measure.objectives'); }); // Display objectives
         Route::get('/measure/ghgcalculator', function() { return view('measure.ghgcalculator'); }); // Display ghg calculator
      }
      
      /**
      * Staff routes
      */
      if(!config('ledningssystemet.disable_staff'))
      {
         Route::get('/staff/employees', function() { return view('staff.employees'); }); // Display personnel level information
         Route::get('/staff/roles', function() { return view('staff.roles'); }); // Display role level information
         Route::get('/staff/qualifications', function() { return view('staff.qualifications'); }); // Training, certifications and more
         Route::get('/staff/competence', function() { return view('staff.competence'); }); // Competences
      }
      
      /**
      * Management routes
      */
      Route::get('/management/activityflowtemplates', function() { return view('management.activityflowtemplates'); }); // Display activity flow templates
      Route::get('/management/activityflow', function() { return view('management.activityflow'); }); // Display activity flow
      Route::get('/management/activities', function() { return view('management.activities'); }); // Display activities
      Route::get('/management/documentlibrary', function() { return view('management.documentlibrary'); }); // Display document library
      Route::get('/management/assessment', function() { return view('management.assessment'); }); // Display assessment settings
      Route::get('/management/tags', function() { return view('management.tags'); }); // Display tags
      Route::get('/management/suppliercategories', function() { return view('management.suppliercategories'); }); // Display supplier categories
      Route::get('/management/riskprojecttypes', function() { return view('management.riskprojecttypes'); }); // Display risk project types
      Route::get('/management/ignoredrisks', function() { return view('management.ignoredrisks'); }); // Display ignored risks

      if(config('ledningssystemet.exchangeproxy_url')) {
         Route::get('/management/formtemplates', function () {
            return view('management.formtemplates');
         }); // Display form templates

         Route::get('/management/formtemplate/{formtemplate}', function (App\Models\FormTemplate $formtemplate) {
            // Return blade view with form template
            if(auth()->user()->cannot('view', $formtemplate))
               abort(403);

            return view('management.formtemplate', ['template' => $formtemplate]);
         });

      }

      Route::get('/management/documenteditor/{id}', function($id) {
         $libraryDocument = \App\Models\LibraryDocument::where('id', $id)->firstOrFail();

         // Ensure correct contenttype
         if('ledningssystemet/document' != $libraryDocument->contenttype)
            abort(404);

         // Get document version
         $version = \App\Models\DocumentVersion::where('library_document_id', $libraryDocument->id)->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->first();
         if(null == $version)
         {
            // Only the responsible user or a coordinator can create a new version
            if((auth()->user()->id != $libraryDocument->responsible_user_id) &&
               auth()->user()->cannot('create', \App\Models\DocumentVersion::class))
               abort(403);

            $version = new \App\Models\DocumentVersion();
            $version->library_document_id = $libraryDocument->id;
            $version->save();
         }
         else if($version->minor_version == 0)
         {
            // Only the responsible user or a coordinator can create a new version
            if((auth()->user()->id != $libraryDocument->responsible_user_id) &&
               auth()->user()->cannot('create', \App\Models\DocumentVersion::class))
               abort(403);

            $newversion = $version->replicate();
            $newversion->minor_version++;
            $newversion->approved_at=null;
            $newversion->finished_at=null;
            $newversion->created_at=now();
            $newversion->save();
            $version = $newversion;
         }

         return view('management.documenteditor', ['document' => $version]);
      }); // Display Document editor

      /**
      * System admin routes
      */
      Route::get('/systemadmin/departments', function() { return view('systemadmin.departments'); }); // Display departments
      if(!config('ledningssystemet.disable_staff'))
      {
         Route::get('/systemadmin/roles', function() { return view('systemadmin.roles'); }); // Display roles
      }
      Route::get('/systemadmin/access', function() { return view('systemadmin.access'); }); // Display access settings
      Route::get('/systemadmin/users', function() { return view('systemadmin.users'); }); // Display users
      Route::get('/systemadmin/apitokens', function() { return view('systemadmin.apitokens'); }); // Display API tokens
      Route::get('/systemadmin/sites', function() { return view('systemadmin.sites'); }); // Display Sites
      Route::get('/systemadmin/swagger', function() { return view('systemadmin.swagger'); }); // Display Swagger
      Route::get('/systemadmin/customproperties', function() { return view('systemadmin.customproperties'); }); // Custom properties settings

      /**
      * Mail preview routes
      */
      Route::get('/mail/statusoverview', function() { return new App\Mail\StatusOverview(auth()->user()); }); // Status mail
      
      /**
      * i18n
      */
      Route::get('/i18n/javascript', function() { 
         return Response::make(view('i18n.javascript'))->header('Content-Type', 'text/javascript'); }); // Translate javascript

   });   
});
