<?php /** @noinspection PhpUndefinedFieldInspection */

/** @noinspection PhpParamsInspection */

namespace Ledningssystemet\Ledningssystemet\Providers;

use App\Models\ActivityLog;
use Ledningssystemet\Ledningssystemet\Providers\FortifyServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
   /**
    * Register any application services.
    *
    * @return void
    */
   public function register()
   {
      $this->app->register(FortifyServiceProvider::class);
   }

   protected static function shouldSkipGlobalHistory(Model $model): bool
   {
      return $model instanceof ActivityLog;
   }

   protected static function writeGlobalHistory(Model $model, string $event, string $action, array $modified = []): void
   {
      if(self::shouldSkipGlobalHistory($model))
         return;

      $causer = request()->user();
      $causerName = (null !== $causer && isset($causer->name) && '' !== trim((string) $causer->name))
         ? $causer->name
         : 'SYSTEM';

      $builder = activity('model-history')
         ->performedOn($model)
         ->event($event)
         ->withProperties([
            'action' => $action,
            'created_by' => $causerName,
            'modified' => $modified,
         ]);

      if(null !== $causer)
         $builder->causedBy($causer);

      $builder->log($event);
   }

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public function boot(): void
   {
      /* Register event listener for cache flush on database modification, except for session update.
         This is a very ugly, yet powerful, way of at least making sure that any cached data is valid
      */
      DB::listen(function (QueryExecuted $query) {
         if (  (0 !== stripos($query->sql, 'select ')) &&
               (false === stripos($query->sql, '`sessions`'))
            ) {
            Cache::flush();
         }
      });

      // Centralized model validation to avoid repeating identical saving hooks.
      Model::saving(function ($model) {
         if (!method_exists($model, 'getValidationRules')) {
            return;
         }

         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });

      Model::created(function ($model) {
         if(!$model instanceof Model)
            return;

         self::writeGlobalHistory($model, 'created', 'C');
      });

      Model::updated(function ($model) {
         if(!$model instanceof Model || self::shouldSkipGlobalHistory($model))
            return;

         $modified = [];
         foreach(array_keys($model->getDirty()) as $dirtyField)
         {
            if('updated_at' == $dirtyField)
               continue;

            $modified[$dirtyField] = true;
         }

         if(0 === count($modified))
            return;

         self::writeGlobalHistory($model, 'updated', 'U', $modified);
      });

      Model::deleted(function ($model) {
         if(!$model instanceof Model)
            return;

         self::writeGlobalHistory($model, 'deleted', 'D');
      });

      Model::resolveRelationUsing('history', function (Model $model): MorphMany {
         return $model->morphMany(ActivityLog::class, 'subject');
      });

      /* Define access levels for features */

      // Superadmin bypass - always grant access
      Gate::before(function (User $user, string $ability) {
         if ($user->hasPermissionTo('superadmin.edit')) {
            return true;
         }
      });

      // AI-features
      Gate::define('useai', function (User $user) {
         return (config('ledningssystemet.openai_endpoint') && $user->hasAnyPermission(['ai.edit']));
      });

      Gate::define('publishdocument', function (User $user, $document) {
         if(is_string($document))
            return $user->hasAnyPermission(['documentpublisher.edit']);
         else
            return $user->hasAnyPermission(['documentpublisher.edit']) && ($document->approver_id == $user->id);
      });

      /* Define access levels for model actions */
      // Indexing (list all)
      Gate::define('index', function (User $user, $model) {
         switch (is_string($model) ? $model : get_class($model)) {
            case 'App\Models\Customer':
               return $user->hasAnyPermission(['customers.read', 'customers.edit']);

            case 'App\Models\ComplianceEvaluation':
            case 'App\Models\ComplianceEvaluationRequirementFinding':
            case 'App\Models\ComplianceEvaluationRequirement':
            case 'App\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.read', 'complianceevaluations.edit']);

            case 'App\Models\Requirement':
            case 'App\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.read', 'requirements.edit']);

            case 'App\Models\Process':
            case 'App\Models\ProcessActivity':
            case 'App\Models\ProcessHref':
            case 'App\Models\InformationType':
            case 'App\Models\Asset':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'App\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.read', 'suppliers.edit']);

            case 'App\Models\Control':
               return $user->hasAnyPermission(['controls.read', 'controls.edit']);

            case 'App\Models\RiskProject':
               // User may view risk projects if the are part of one or have the correct access rights
               if($user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']))
                  return true;

               if(\App\Models\RiskProject::where('responsible_user_id', $user->id)->exists())
                  return true;

               if(\App\Models\RiskProject::whereHas('int_users', function($q) use ($user) {
                  $q->where('users.id', $user->id);
               })->exists())
                  return true;

               return false;

            case 'App\Models\Risk':
               return ((0 < intval(request()->input('risk_project_id', '0'))) || $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']));

            case 'App\Models\Finding':
               return (!config('ledningssystemet.disable_finding')) && $user->hasAnyPermission(['findings.read', 'findings.edit']);

            case 'App\Models\Incident':
            case 'App\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.read', 'incidents.edit']);

            case 'App\Models\ActivityFlowTemplateItem':
            case 'App\Models\AvailabilityClass':
            case 'App\Models\ConfidentialityClass':
            case 'App\Models\IntegrityClass':
            case 'App\Models\ConsequenceLevel':
            case 'App\Models\Department':
            case 'App\Models\FormTemplate':
            case 'App\Models\ProbabilityLevel':
            case 'App\Models\RiskLevel':
            case 'App\Models\Tag':
            case 'App\Models\RiskProjectType':
            case 'App\Models\RiskProjectTypeRiskTemplate':
            case 'App\Models\GhgCategory':
            case 'App\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\ConfidentialityGround':
            case 'App\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'App\Models\LibraryDocument':
            case 'App\Models\DocumentVersion':
               // Authorization handled by models themselves
            return true;

            case 'App\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SubjectCategory':
            case 'App\Models\DataCategory':
            case 'App\Models\LegalBasis':
            case 'App\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SupplierCategory':
            case 'App\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.read', 'suppliers.edit']);

            case 'App\Models\SustainabilityAspect':
            case 'App\Models\SustainabilityMetric':
            case 'App\Models\SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\User':
            case 'App\Models\Site':
            case 'App\Models\AccessGroup':
            case 'App\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\ProcessPerformanceMetric':
            case 'App\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.read', 'processmetrics.edit']);

            case 'App\Models\Objective':
            case 'App\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.read', 'objectives.edit']);


            case 'App\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'App\Models\EmployeeRole':
            case 'App\Models\Qualification':
            case 'App\Models\QualificationRole':
            case 'App\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'App\Models\QualificationUser':
            case 'App\Models\UserCompetence':
            {
               if(config('ledningssystemet.disable_staff')) return false;

               if(is_string($model) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']))
                  return true;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               return ($model->manager_user_id == $user->id);
            }

            case 'App\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.read', 'sustainabilityaspects.edit']);

            case 'App\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.read', 'chemicalregister.edit']);

            case 'App\Models\Agreement':
               return $user->hasAnyPermission(['agreements.read', 'agreements.edit']);

            case 'App\Models\IgnoredRisk':
               return $user->hasAnyPermission(['riskadministrator.edit']);

            case 'App\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'App\Models\GhgFactor':
            case 'App\Models\GhgFactorReport':
               return $user->hasAnyPermission(['ghg.read', 'ghg.edit']);

            // Authorization handled by models themselves
            case 'App\Models\Activity':
            case 'App\Models\ActivityFlow':
            case 'App\Models\ActivityFlowTemplate':
            case 'App\Models\UserNotificationChannel':
            case 'App\Models\ActivityLog':
            case 'App\Models\ObjectMessage':
            case 'App\Models\Me':
            case 'App\Models\Competence':
            case 'App\Models\CompetenceLevel':
            case 'App\Models\ControlAction':
            case 'App\Models\File':
            case 'App\Models\Relation':
            case 'App\Models\Form':
               return true;
         }

         return false;
      });

      // View (list single)
      Gate::define('view', function (User $user, $model) {
         switch (is_string($model) ? $model : get_class($model)) {
            case 'App\Models\Customer':
               return $user->hasAnyPermission(['customers.read', 'customers.edit']);

            case 'App\Models\Me':
            case 'App\Models\Competence':
            case 'App\Models\CompetenceLevel':
               return true;

            case 'App\Models\ActivityLog':
            case 'App\Models\ObjectMessage':
               return $user->can('view', $model->object_type::findOrFail($model->object_id));


            case 'App\Models\ComplianceEvaluation':
            case 'App\Models\ComplianceEvaluationRequirementFinding':
            case 'App\Models\ComplianceEvaluationRequirement':
            case 'App\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.read', 'complianceevaluations.edit']);

            case 'App\Models\Requirement':
            case 'App\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.read', 'requirements.edit']);


            case 'App\Models\Process':
            case 'App\Models\ProcessActivity':
            case 'App\Models\ProcessHref':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'App\Models\InformationType':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'App\Models\Asset':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'App\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.read', 'suppliers.edit']);

            case 'App\Models\Control':
               return $user->hasAnyPermission(['controls.read', 'controls.edit']);

            case 'App\Models\Risk':

               if (is_string($model))
                  return $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']);


               if (null == $model->risk_project_id) {
                  if ($user->hasAnyPermission(['riskadministrator.edit', 'riskall.edit'])) // If user is admin or have riskall privilege, then user may view any risk
                     return true;
                  if ((null != $model->riskowner_id) && ($model->riskowner_id == $user->id)) // If user is risk owner, then user is allowed to view this risk
                     return true;
                  if ($user->hasAnyPermission(['riskdepartment.edit']) && ($user->int_departments()->where('departments.id', $model->department_id)->exists()))
                     return true;
               } else // Risk project risk
               {
                  if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may view any risk
                     return true;
                  if ($model->int_risk_project->responsible_user_id == $user->id) // If user is responsible, then user may view the risk project
                     return true;
                  if (false !== array_search($user->id, $model->int_risk_project->int_users()->pluck('users.id')->all()))
                     return true;
               }

               return false;

            case 'App\Models\RiskProject':
               if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may view any risk project
                  return true;
               if ($model->responsible_user_id == $user->id) // If user is responsible, then user may view the risk project
                  return true;
               if (false !== array_search($user->id, $model->int_users()->pluck('users.id')->all()))  // If user is a participant, then user may view the risk project
                  return true;

               return false;

            case 'App\Models\Finding':
               return (!config('ledningssystemet.disable_finding')) && $user->hasAnyPermission(['findings.read', 'findings.edit']);

            case 'App\Models\Incident':
            case 'App\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.read', 'incidents.edit']);


            case 'App\Models\ActivityFlowTemplate':
            case 'App\Models\ActivityFlowTemplateItem':
            case 'App\Models\AvailabilityClass':
            case 'App\Models\ConfidentialityClass':
            case 'App\Models\IntegrityClass':
            case 'App\Models\ConsequenceLevel':
            case 'App\Models\Department':
            case 'App\Models\ProbabilityLevel':
            case 'App\Models\RiskLevel':
            case 'App\Models\Tag':
            case 'App\Models\RiskProjectType':
            case 'App\Models\RiskProjectTypeRiskTemplate':
            case 'App\Models\GhgCategory':
            case 'App\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\ConfidentialityGround':
            case 'App\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'App\Models\LibraryDocument':
               return true;

            case 'App\Models\DocumentVersion':
               if(is_string($model))
                  return false;
               else
                  return (($user->id == $model->approver_id) || ($user->id == $model->int_library_document->responsible_user_id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'App\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SubjectCategory':
            case 'App\Models\DataCategory':
            case 'App\Models\LegalBasis':
            case 'App\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SupplierCategory':
            case 'App\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.read', 'suppliers.edit']);

            case 'App\Models\SustainabilityAspect':
            case 'App\Models\SustainabilityMetric':
            case 'App\Models\SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\User':
            case 'App\Models\Site':
            case 'App\Models\AccessGroup':
            case 'App\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\ProcessPerformanceMetric':
            case 'App\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.read', 'processmetrics.edit']);

            case 'App\Models\Objective':
            case 'App\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.read', 'objectives.edit']);


            case 'App\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'App\Models\EmployeeRole':
            case 'App\Models\Qualification':
            case 'App\Models\QualificationRole':
            case 'App\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'App\Models\QualificationUser':
            case 'App\Models\UserCompetence':
            {
               if(config('ledningssystemet.disable_staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return true;

               return ($model->int_user->manager_user_id == $user->id);
            }


            case 'App\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.read', 'sustainabilityaspects.edit']);

            case 'App\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.read', 'chemicalregister.edit']);

            case 'App\Models\Activity':
            case 'App\Models\ActivityFlow':
               return (($model->responsible_user_id == $user->id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'App\Models\UserNotificationChannel':
               return (($model->user_id == $user->id) || $user->hasAnyPermission(['systemadministrator.edit']));

            case 'App\Models\ControlAction':
               return (($model->responsible_id == $user->id) || $user->hasAnyPermission(['allcontrolactions.read']));

            case 'App\Models\File':
               return $user->can('view', $model->object_type::findOrFail($model->object_id));

            case 'App\Models\Agreement':
               return $user->hasAnyPermission(['agreements.read', 'agreements.edit']);

            case 'App\Models\IgnoredRisk':
               return $user->hasAnyPermission(['riskadministrator.edit']);

            case 'App\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\FormTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\Form':
               return $user->hasAnyPermission(['forms.edit']) || $user->can('view', $model->object_type::findOrFail($model->object_id));

            case 'App\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'App\Models\Relation':
               return $user->can('view', $model->object_type::findOrFail($model->object_id));

            case 'App\Models\GhgFactor':
            case 'App\Models\GhgFactorReport':
               return $user->hasAnyPermission(['ghg.read', 'ghg.edit']);

         }

         return false;
      });

      // Create
      Gate::define('create', function (User $user, $model) {
         switch (is_string($model) ? $model : get_class($model)) {
            case 'App\Models\Customer':
               return $user->hasAnyPermission(['customers.edit']);

            case 'App\Models\ComplianceEvaluation':
            case 'App\Models\ComplianceEvaluationRequirementFinding':
            case 'App\Models\ComplianceEvaluationRequirement':
            case 'App\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.edit']);

            case 'App\Models\Requirement':
            case 'App\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.edit']);

            case 'App\Models\Process':
            case 'App\Models\ProcessActivity':
            case 'App\Models\ProcessHref':
               return $user->hasAnyPermission(['processes.edit']);

            case 'App\Models\InformationType':
               return $user->hasAnyPermission(['processes.edit']);

            case 'App\Models\Asset':
               return $user->hasAnyPermission(['processes.edit']);

            case 'App\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.edit']);

            case 'App\Models\Control':
               return $user->hasAnyPermission(['controls.edit']);

            case 'App\Models\Risk':
               return true;

            case 'App\Models\RiskProject':
               return $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']);

            case 'App\Models\Finding':
               return (!config('ledningssystemet.disable_finding'));

            case 'App\Models\Incident':
            case 'App\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.edit']);

            case 'App\Models\ActivityFlowTemplateItem':
            case 'App\Models\AvailabilityClass':
            case 'App\Models\ConfidentialityClass':
            case 'App\Models\IntegrityClass':
            case 'App\Models\ConsequenceLevel':
            case 'App\Models\Department':
            case 'App\Models\ProbabilityLevel':
            case 'App\Models\RiskLevel':
            case 'App\Models\Tag':
            case 'App\Models\RiskProjectType':
            case 'App\Models\RiskProjectTypeRiskTemplate':
            case 'App\Models\FormTemplate':
            case 'App\Models\GhgCategory':
            case 'App\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\ConfidentialityGround':
            case 'App\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'App\Models\LibraryDocument':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SubjectCategory':
            case 'App\Models\DataCategory':
            case 'App\Models\LegalBasis':
            case 'App\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SupplierCategory':
            case 'App\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SustainabilityAspect':
            case 'App\Models\SustainabilityMetric':
            case 'App\Models\SustainabilityMetricLevel':
            case 'App\Models\ActivityFlowTemplate':

               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\User':
            case 'App\Models\Site':
            case 'App\Models\AccessGroup':
            case 'App\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\ProcessPerformanceMetric':
            case 'App\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.edit']);

            case 'App\Models\Objective':
            case 'App\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.edit']);


            case 'App\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'App\Models\EmployeeRole':
            case 'App\Models\Qualification':
            case 'App\Models\QualificationRole':
            case 'App\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'App\Models\QualificationUser':
            case 'App\Models\Competence':
            case 'App\Models\CompetenceLevel':
            {
               if(config('ledningssystemet.disable_staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return ('App\Models\Competence' != $model);

               return ($model->user_id && \App\Models\User::where('id', $model->user_id)->where('manager_user_id', $user->id)->exists());
            }

            case 'App\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.edit']);

            case 'App\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.edit']);

            case 'App\Models\Activity':
               return true;

            case 'App\Models\ActivityFlow':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\Agreement':
               return $user->hasAnyPermission(['agreements.edit']);

            case 'App\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\UserNotificationChannel':
            case 'App\Models\ObjectMessage':
            case 'App\Models\ControlAction':
            case 'App\Models\File':
            case 'App\Models\Relation':
               return true;

            case 'App\Models\IgnoredRisk':
               return false;

            case 'App\Models\Form':
            case 'App\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'App\Models\GhgFactor':
            case 'App\Models\GhgFactorReport':
               return $user->hasAnyPermission(['ghg.edit']);

         }

         return false;
      });

      // Update
      Gate::define('update', function (User $user, $model) {
         // If an object is assigned a user, then the user is allowed to edit it
         if(!is_string($model) && property_exists($model, 'responsible_user_id') && ($user->id == $model->responsible_user_id))
            return true;
         
         if(!is_string($model) && property_exists($model, 'responsible_id') && ($user->id == $model->responsible_id))
            return true;

         switch (is_string($model) ? $model : get_class($model)) {
            case 'App\Models\Customer':
               return $user->hasAnyPermission(['customers.edit']);

            case 'App\Models\ComplianceEvaluation':
            case 'App\Models\ComplianceEvaluationRequirementFinding':
            case 'App\Models\ComplianceEvaluationRequirement':
            case 'App\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.edit']);

            case 'App\Models\Requirement':
            case 'App\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.edit']);

            case 'App\Models\Process':
            case 'App\Models\ProcessActivity':
            case 'App\Models\ProcessHref':
               return $user->hasAnyPermission(['processes.edit']);

            case 'App\Models\InformationType':
               return $user->hasAnyPermission(['processes.edit']);

            case 'App\Models\Asset':
               return $user->hasAnyPermission(['processes.edit']);

            case 'App\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.edit']);

            case 'App\Models\Control':
               return $user->hasAnyPermission(['controls.edit']);


            case 'App\Models\Risk':
               if (is_string($model) && $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']))
                  return true;

               else if(is_string($model))
                  return false;

               if (null == $model->risk_project_id) {
                  if ($user->hasAnyPermission(['riskadministrator.edit', 'riskall.edit'])) // If user is admin or have riskall privilege, then user may edit any risk
                     return true;

                  if ((null != $model->riskowner_id) && ($model->riskowner_id == $user->id)) // If user is risk owner, then user is allowed to edit this risk
                     return true;

                  if ($user->hasAnyPermission(['riskdepartment.edit']) && ($user->int_departments()->where('departments.id', $model->department_id)->exists()))
                     return true;
               } else // Risk project risk
               {
                  if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may edit any risk
                     return true;

                  if ($model->int_risk_project->responsible_user_id == $user->id) // If user is responsible for the risk project, then user may edit the risk
                     return true;

                  // If user is part of for the risk project, then user may edit the risk
                  if (false !== array_search($user->id, $model->int_risk_project->int_users()->pluck('users.id')->all()))
                     return true;
               }
               return false;

            case 'App\Models\RiskProject':
               if (is_string($model))
                  return true;


               if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may edit any risk project
                  return true;

               if ($model->responsible_user_id == $user->id) // If user is responsible, then user may edit the risk project
                  return true;

               return false;

            case 'App\Models\Finding':
               return (!config('ledningssystemet.disable_finding')) && $user->hasAnyPermission(['findings.edit']);

            case 'App\Models\Incident':
            case 'App\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.edit']);

            case 'App\Models\ActivityFlowTemplateItem':
            case 'App\Models\AvailabilityClass':
            case 'App\Models\ConfidentialityClass':
            case 'App\Models\IntegrityClass':
            case 'App\Models\ConsequenceLevel':
            case 'App\Models\Department':
            case 'App\Models\ProbabilityLevel':
            case 'App\Models\RiskLevel':
            case 'App\Models\Tag':
            case 'App\Models\RiskProjectType':
            case 'App\Models\RiskProjectTypeRiskTemplate':
            case 'App\Models\FormTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\GhgCategory':
            case 'App\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\ConfidentialityGround':
            case 'App\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'App\Models\LibraryDocument':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);

               return ($user->hasAnyPermission(['managementtools.edit']) ||
                      ($user->id == $model->responsible_user_id));

            case 'App\Models\DocumentVersion':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);
               else
                  return ($user->hasAnyPermission(['managementtools.edit']) || ($model->int_library_document->responsible_user_id == auth()->user()->id));

            case 'App\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SubjectCategory':
            case 'App\Models\DataCategory':
            case 'App\Models\LegalBasis':
            case 'App\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\SupplierCategory':
            case 'App\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.edit']);

            case 'App\Models\SustainabilityAspect':
            case 'App\Models\SustainabilityMetric':
            case 'App\Models\SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\User':
            case 'App\Models\Site':
            case 'App\Models\AccessGroup':
            case 'App\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\ProcessPerformanceMetric':
            case 'App\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.edit']);

            case 'App\Models\Objective':
               if(is_string($model))
                  return $user->hasAnyPermission(['objectives.edit']);
               else
                  return (null == $model->archived_at) && $user->hasAnyPermission(['objectives.edit']);

            case 'App\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.edit']);

            case 'App\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'App\Models\EmployeeRole':
            case 'App\Models\Qualification':
            case 'App\Models\QualificationRole':
            case 'App\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'App\Models\QualificationUser':
            case 'App\Models\CompetenceLevel':
            case 'App\Models\Competence':
            {
               if(config('ledningssystemet.disable_staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return true;

               if(('App\Models\Competence' == get_class($model)) && request()->has('user_id'))
                   return (\App\Models\User::where('id', request()->get('user_id'))->where('manager_user_id', $user->id)->exists());

               return ($model->user_id && \App\Models\User::where('id', $model->user_id)->where('manager_user_id', $user->id)->exists());
            }



            case 'App\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.edit']);

            case 'App\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.edit']);

            // Authorization handled by models themselves
            case 'App\Models\Activity':
            case 'App\Models\ActivityFlow':
               if (is_string($model))
                  return true;

               return (($model->responsible_user_id == $user->id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'App\Models\ControlAction':
               if (is_string($model))
                  return true;

               return ($model->responsible_id == $user->id);

            case 'App\Models\ActivityFlowTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);


            case 'App\Models\UserNotificationChannel':
               if (is_string($model))
                  return true;

               return (($model->user_id == $user->id) || $user->hasAnyPermission(['systemadministrator.edit']));

            case 'App\Models\File':
               if (is_string($model))
                  return true;

               return $user->can('update', $model->obj());

            case 'App\Models\Agreement':
               return $user->hasAnyPermission(['agreements.edit']);

            case 'App\Models\IgnoredRisk':
               return false;

            case 'App\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'App\Models\Form':
            case 'App\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'App\Models\Relation':
               return $user->can('update', $model->object_type::findOrFail($model->object_id));

            case 'App\Models\GhgFactor':
            case 'App\Models\GhgFactorReport':
               return $user->hasAnyPermission(['ghg.edit']);

         }
         return false;
      });

      // Delete
      Gate::define('delete', function (User $user, $model) {

         /* If a user can update, then it can delete for most objects */
         if (is_string($model)) {
            switch($model) {
               case 'App\Models\IgnoredRisk':
                  return $user->hasAnyPermission(['riskadministrator.edit']);
            }

            return $user->can('update', $model);
         }

         switch (is_string($model) ? $model : get_class($model)) {
            /* If a user can update, then it can delete for most objects */
            case 'App\Models\Customer':
            case 'App\Models\ComplianceEvaluation':
            case 'App\Models\ComplianceEvaluationRequirementFinding':
            case 'App\Models\ComplianceEvaluationRequirement':
            case 'App\Models\Requirement':
            case 'App\Models\RequirementSource':
            case 'App\Models\Process':
            case 'App\Models\ProcessActivity':
            case 'App\Models\ProcessHref':
            case 'App\Models\Supplier':
            case 'App\Models\Control':
            case 'App\Models\Incident':
            case 'App\Models\IncidentLog':
            case 'App\Models\Finding':
            case 'App\Models\RiskProject':
            case 'App\Models\ActivityFlowTemplateItem':
            case 'App\Models\AvailabilityClass':
            case 'App\Models\ConfidentialityClass':
            case 'App\Models\ConsequenceLevel':
            case 'App\Models\Department':
            case 'App\Models\ProbabilityLevel':
            case 'App\Models\RiskLevel':
            case 'App\Models\Tag':
            case 'App\Models\Role':
            case 'App\Models\SubjectCategory':
            case 'App\Models\DataCategory':
            case 'App\Models\IntegrityClass':
            case 'App\Models\LegalBasis':
            case 'App\Models\RecipientCategory':
            case 'App\Models\SupplierCategory':
            case 'App\Models\SupplierRequirement':
            case 'App\Models\SustainabilityAspect':
            case 'App\Models\SustainabilityMetric':
            case 'App\Models\SustainabilityMetricLevel':
            case 'App\Models\User':
            case 'App\Models\AccessGroup':
            case 'App\Models\PersonalAccessToken':
            case 'App\Models\ProcessPerformanceMetric':
            case 'App\Models\ProcessPerformanceMetricReport':
            case 'App\Models\Objective':
            case 'App\Models\ObjectiveProcessPerformanceMetric':
            case 'App\Models\EmployeeRole':
            case 'App\Models\Qualification':
            case 'App\Models\QualificationRole':
            case 'App\Models\QualificationUser':
            case 'App\Models\RoleCompetence':
            case 'App\Models\Competence':
            case 'App\Models\CompetenceLevel':
            case 'App\Models\ProcessSustainabilityAspect':
            case 'App\Models\Chemical':
            case 'App\Models\ActivityFlow':
            case 'App\Models\Activity':
            case 'App\Models\UserNotificationChannel':
            case 'App\Models\ActivityFlowTemplate':
            case 'App\Models\ControlAction':
            case 'App\Models\Site':
            case 'App\Models\RiskProjectType':
            case 'App\Models\RiskProjectTypeRiskTemplate':
            case 'App\Models\Agreement':
            case 'App\Models\ConfidentialityGround':
            case 'App\Models\Diary':
            case 'App\Models\CustomProperty':
            case 'App\Models\FormTemplate':
            case 'App\Models\Form':
            case 'App\Models\FormRelation':
            case 'App\Models\Relation':
            case 'App\Models\GhgCategory':
            case 'App\Models\GhgConversionFactor':
            case 'App\Models\GhgFactor':
            case 'App\Models\GhgFactorReport':
               return $user->can('update', $model);

            // Information types and assets that are connected to a process cannot be deleted
            case 'App\Models\InformationType':
               return ($user->can('update', $model) && !count($model->int_processes()));

            case 'App\Models\Asset':
               return ($user->can('update', $model) && !$model->int_processes()->count());

            case 'App\Models\Risk':
               if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may delete any risk
                  return true;

               return $user->can('update', $model);


            case 'App\Models\ObjectMessage':
               return $user->can('update', $model->object_type::findOrFail($model->object_id));

            case 'App\Models\File':
               if (is_string($model))
                  return true;

               return $user->can('update', $model->obj());

            case 'App\Models\IgnoredRisk':
               return $user->hasAnyPermission(['riskadministrator.edit']);

            case 'App\Models\LibraryDocument':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'App\Models\DocumentVersion':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);
               else
                  return !$model->approved_at && $user->can('update', $model);

         }

         return false;
      });
   }
}
