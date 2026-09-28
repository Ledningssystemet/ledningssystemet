<?php /** @noinspection PhpUndefinedFieldInspection */

/** @noinspection PhpParamsInspection */

namespace Ledningssystemet\Ledningssystemet\Providers;

use Ledningssystemet\Ledningssystemet\Models\ActivityLog;
use Ledningssystemet\Ledningssystemet\Providers\FortifyServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Ledningssystemet\Ledningssystemet\Models\User;

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

   protected static function resolveObject(Model $model): Model
   {
      $objectType = $model->getAttribute('object_type');
      $class = is_string($objectType) ? (str_contains($objectType, '\\') ? $objectType : 'Ledningssystemet\\Ledningssystemet\\Models\\'.$objectType) : null;
      if (!is_string($class) || !str_starts_with($class, 'Ledningssystemet\\Ledningssystemet\\Models\\') || !is_subclass_of($class, Model::class)) {
         abort(404);
      }

      return $class::findOrFail($model->getAttribute('object_id'));
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

      Event::listen('eloquent.created: *', function (string $eventName, array $payload) {
         $model = $payload[0] ?? null;
         if(!$model instanceof Model)
            return;

         self::writeGlobalHistory($model, 'created', 'C');
      });

      Event::listen('eloquent.updated: *', function (string $eventName, array $payload) {
         $model = $payload[0] ?? null;
         if(!$model instanceof Model || self::shouldSkipGlobalHistory($model))
            return;

         $modified = [];
         foreach(array_keys($model->getChanges()) as $dirtyField)
         {
            if('updated_at' == $dirtyField)
               continue;

            $modified[$dirtyField] = true;
         }

         if(0 === count($modified))
            return;

         self::writeGlobalHistory($model, 'updated', 'U', $modified);
      });

      Event::listen('eloquent.deleted: *', function (string $eventName, array $payload) {
         $model = $payload[0] ?? null;
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
            case 'Ledningssystemet\Ledningssystemet\Models\Customer':
               return $user->hasAnyPermission(['customers.read', 'customers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluation':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementFinding':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirement':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.read', 'complianceevaluations.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Requirement':
            case 'Ledningssystemet\Ledningssystemet\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.read', 'requirements.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Process':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessActivity':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessHref':
            case 'Ledningssystemet\Ledningssystemet\Models\InformationType':
            case 'Ledningssystemet\Ledningssystemet\Models\Asset':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.read', 'suppliers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Control':
               return $user->hasAnyPermission(['controls.read', 'controls.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\RiskProject':
               // User may view risk projects if the are part of one or have the correct access rights
               if($user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']))
                  return true;

               if(\Ledningssystemet\Ledningssystemet\Models\RiskProject::where('responsible_user_id', $user->id)->exists())
                  return true;

               if(\Ledningssystemet\Ledningssystemet\Models\RiskProject::whereHas('int_users', function($q) use ($user) {
                  $q->where('users.id', $user->id);
               })->exists())
                  return true;

               return false;

            case 'Ledningssystemet\Ledningssystemet\Models\Risk':
               return ((0 < intval(request()->input('risk_project_id', '0'))) || $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\Finding':
               return (!config('ledningssystemet.disable_finding')) && $user->hasAnyPermission(['findings.read', 'findings.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Incident':
            case 'Ledningssystemet\Ledningssystemet\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.read', 'incidents.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplateItem':
            case 'Ledningssystemet\Ledningssystemet\Models\AvailabilityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\IntegrityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConsequenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Department':
            case 'Ledningssystemet\Ledningssystemet\Models\FormTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\ProbabilityLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Tag':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectType':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectTypeRiskTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityGround':
            case 'Ledningssystemet\Ledningssystemet\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\LibraryDocument':
            case 'Ledningssystemet\Ledningssystemet\Models\DocumentVersion':
               // Authorization handled by models themselves
            return true;

            case 'Ledningssystemet\Ledningssystemet\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SubjectCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\DataCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\LegalBasis':
            case 'Ledningssystemet\Ledningssystemet\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SupplierCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.read', 'suppliers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityAspect':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\User':
            case 'Ledningssystemet\Ledningssystemet\Models\Site':
            case 'Ledningssystemet\Ledningssystemet\Models\AccessGroup':
            case 'Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.read', 'processmetrics.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Objective':
            case 'Ledningssystemet\Ledningssystemet\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.read', 'objectives.edit']);


            case 'Ledningssystemet\Ledningssystemet\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\EmployeeRole':
            case 'Ledningssystemet\Ledningssystemet\Models\Qualification':
            case 'Ledningssystemet\Ledningssystemet\Models\QualificationRole':
            case 'Ledningssystemet\Ledningssystemet\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\QualificationUser':
            case 'Ledningssystemet\Ledningssystemet\Models\UserCompetence':
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

            case 'Ledningssystemet\Ledningssystemet\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.read', 'sustainabilityaspects.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.read', 'chemicalregister.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Agreement':
               return $user->hasAnyPermission(['agreements.read', 'agreements.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\IgnoredRisk':
               return $user->hasAnyPermission(['riskadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactor':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactorReport':
               return $user->hasAnyPermission(['ghg.read', 'ghg.edit']);

            // Authorization handled by models themselves
            case 'Ledningssystemet\Ledningssystemet\Models\Activity':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlow':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityLog':
            case 'Ledningssystemet\Ledningssystemet\Models\ObjectMessage':
            case 'Ledningssystemet\Ledningssystemet\Models\Me':
            case 'Ledningssystemet\Ledningssystemet\Models\Competence':
            case 'Ledningssystemet\Ledningssystemet\Models\CompetenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\ControlAction':
            case 'Ledningssystemet\Ledningssystemet\Models\File':
            case 'Ledningssystemet\Ledningssystemet\Models\Relation':
            case 'Ledningssystemet\Ledningssystemet\Models\Form':
               return true;
         }

         return false;
      });

      // View (list single)
      Gate::define('view', function (User $user, $model) {
         switch (is_string($model) ? $model : get_class($model)) {
            case 'Ledningssystemet\Ledningssystemet\Models\Customer':
               return $user->hasAnyPermission(['customers.read', 'customers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Me':
            case 'Ledningssystemet\Ledningssystemet\Models\Competence':
            case 'Ledningssystemet\Ledningssystemet\Models\CompetenceLevel':
               return true;

            case 'Ledningssystemet\Ledningssystemet\Models\ActivityLog':
            case 'Ledningssystemet\Ledningssystemet\Models\ObjectMessage':
               return $user->can('view', self::resolveObject($model));


            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluation':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementFinding':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirement':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.read', 'complianceevaluations.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Requirement':
            case 'Ledningssystemet\Ledningssystemet\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.read', 'requirements.edit']);


            case 'Ledningssystemet\Ledningssystemet\Models\Process':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessActivity':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessHref':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\InformationType':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Asset':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.read', 'suppliers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Control':
               return $user->hasAnyPermission(['controls.read', 'controls.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Risk':

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

            case 'Ledningssystemet\Ledningssystemet\Models\RiskProject':
               if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may view any risk project
                  return true;
               if ($model->responsible_user_id == $user->id) // If user is responsible, then user may view the risk project
                  return true;
               if (false !== array_search($user->id, $model->int_users()->pluck('users.id')->all()))  // If user is a participant, then user may view the risk project
                  return true;

               return false;

            case 'Ledningssystemet\Ledningssystemet\Models\Finding':
               return (!config('ledningssystemet.disable_finding')) && $user->hasAnyPermission(['findings.read', 'findings.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Incident':
            case 'Ledningssystemet\Ledningssystemet\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.read', 'incidents.edit']);


            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplateItem':
            case 'Ledningssystemet\Ledningssystemet\Models\AvailabilityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\IntegrityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConsequenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Department':
            case 'Ledningssystemet\Ledningssystemet\Models\ProbabilityLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Tag':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectType':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectTypeRiskTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityGround':
            case 'Ledningssystemet\Ledningssystemet\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\LibraryDocument':
               return true;

            case 'Ledningssystemet\Ledningssystemet\Models\DocumentVersion':
               if(is_string($model))
                  return false;
               else
                  return (($user->id == $model->approver_id) || ($user->id == $model->int_library_document->responsible_user_id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SubjectCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\DataCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\LegalBasis':
            case 'Ledningssystemet\Ledningssystemet\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SupplierCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.read', 'suppliers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityAspect':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\User':
            case 'Ledningssystemet\Ledningssystemet\Models\Site':
            case 'Ledningssystemet\Ledningssystemet\Models\AccessGroup':
            case 'Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.read', 'processmetrics.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Objective':
            case 'Ledningssystemet\Ledningssystemet\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.read', 'objectives.edit']);


            case 'Ledningssystemet\Ledningssystemet\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\EmployeeRole':
            case 'Ledningssystemet\Ledningssystemet\Models\Qualification':
            case 'Ledningssystemet\Ledningssystemet\Models\QualificationRole':
            case 'Ledningssystemet\Ledningssystemet\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\QualificationUser':
            case 'Ledningssystemet\Ledningssystemet\Models\UserCompetence':
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


            case 'Ledningssystemet\Ledningssystemet\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.read', 'sustainabilityaspects.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.read', 'chemicalregister.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Activity':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlow':
               return (($model->responsible_user_id == $user->id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel':
               return (($model->user_id == $user->id) || $user->hasAnyPermission(['systemadministrator.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\ControlAction':
               return (($model->responsible_id == $user->id) || $user->hasAnyPermission(['allcontrolactions.read']));

            case 'Ledningssystemet\Ledningssystemet\Models\File':
               return $user->can('view', self::resolveObject($model));

            case 'Ledningssystemet\Ledningssystemet\Models\Agreement':
               return $user->hasAnyPermission(['agreements.read', 'agreements.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\IgnoredRisk':
               return $user->hasAnyPermission(['riskadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\FormTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Form':
               return $user->hasAnyPermission(['forms.edit']) || $user->can('view', self::resolveObject($model));

            case 'Ledningssystemet\Ledningssystemet\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Relation':
               return $user->can('view', self::resolveObject($model));

            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactor':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactorReport':
               return $user->hasAnyPermission(['ghg.read', 'ghg.edit']);

         }

         return false;
      });

      // Create
      Gate::define('create', function (User $user, $model) {
         switch (is_string($model) ? $model : get_class($model)) {
            case 'Ledningssystemet\Ledningssystemet\Models\Customer':
               return $user->hasAnyPermission(['customers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluation':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementFinding':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirement':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Requirement':
            case 'Ledningssystemet\Ledningssystemet\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Process':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessActivity':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessHref':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\InformationType':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Asset':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Control':
               return $user->hasAnyPermission(['controls.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Risk':
               return true;

            case 'Ledningssystemet\Ledningssystemet\Models\RiskProject':
               return $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Finding':
               return (!config('ledningssystemet.disable_finding'));

            case 'Ledningssystemet\Ledningssystemet\Models\Incident':
            case 'Ledningssystemet\Ledningssystemet\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplateItem':
            case 'Ledningssystemet\Ledningssystemet\Models\AvailabilityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\IntegrityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConsequenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Department':
            case 'Ledningssystemet\Ledningssystemet\Models\ProbabilityLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Tag':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectType':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectTypeRiskTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\FormTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityGround':
            case 'Ledningssystemet\Ledningssystemet\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\LibraryDocument':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SubjectCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\DataCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\LegalBasis':
            case 'Ledningssystemet\Ledningssystemet\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SupplierCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityAspect':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetricLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplate':

               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\User':
            case 'Ledningssystemet\Ledningssystemet\Models\Site':
            case 'Ledningssystemet\Ledningssystemet\Models\AccessGroup':
            case 'Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Objective':
            case 'Ledningssystemet\Ledningssystemet\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.edit']);


            case 'Ledningssystemet\Ledningssystemet\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\EmployeeRole':
            case 'Ledningssystemet\Ledningssystemet\Models\Qualification':
            case 'Ledningssystemet\Ledningssystemet\Models\QualificationRole':
            case 'Ledningssystemet\Ledningssystemet\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\QualificationUser':
            case 'Ledningssystemet\Ledningssystemet\Models\Competence':
            case 'Ledningssystemet\Ledningssystemet\Models\CompetenceLevel':
            {
               if(config('ledningssystemet.disable_staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return ('Ledningssystemet\Ledningssystemet\Models\Competence' != $model);

               return ($model->user_id && \Ledningssystemet\Ledningssystemet\Models\User::where('id', $model->user_id)->where('manager_user_id', $user->id)->exists());
            }

            case 'Ledningssystemet\Ledningssystemet\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Activity':
               return true;

            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlow':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Agreement':
               return $user->hasAnyPermission(['agreements.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel':
            case 'Ledningssystemet\Ledningssystemet\Models\ObjectMessage':
            case 'Ledningssystemet\Ledningssystemet\Models\ControlAction':
            case 'Ledningssystemet\Ledningssystemet\Models\File':
            case 'Ledningssystemet\Ledningssystemet\Models\Relation':
               return true;

            case 'Ledningssystemet\Ledningssystemet\Models\IgnoredRisk':
               return false;

            case 'Ledningssystemet\Ledningssystemet\Models\Form':
            case 'Ledningssystemet\Ledningssystemet\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactor':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactorReport':
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
            case 'Ledningssystemet\Ledningssystemet\Models\Customer':
               return $user->hasAnyPermission(['customers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluation':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementFinding':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirement':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Requirement':
            case 'Ledningssystemet\Ledningssystemet\Models\RequirementSource':
               return $user->hasAnyPermission(['requirements.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Process':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessActivity':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessHref':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\InformationType':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Asset':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Supplier':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['suppliers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Control':
               return $user->hasAnyPermission(['controls.edit']);


            case 'Ledningssystemet\Ledningssystemet\Models\Risk':
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

            case 'Ledningssystemet\Ledningssystemet\Models\RiskProject':
               if (is_string($model))
                  return true;


               if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may edit any risk project
                  return true;

               if ($model->responsible_user_id == $user->id) // If user is responsible, then user may edit the risk project
                  return true;

               return false;

            case 'Ledningssystemet\Ledningssystemet\Models\Finding':
               return (!config('ledningssystemet.disable_finding')) && $user->hasAnyPermission(['findings.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Incident':
            case 'Ledningssystemet\Ledningssystemet\Models\IncidentLog':
               return $user->hasAnyPermission(['incidents.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplateItem':
            case 'Ledningssystemet\Ledningssystemet\Models\AvailabilityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\IntegrityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConsequenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Department':
            case 'Ledningssystemet\Ledningssystemet\Models\ProbabilityLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Tag':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectType':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectTypeRiskTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\FormTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\GhgCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityGround':
            case 'Ledningssystemet\Ledningssystemet\Models\Diary':
               return (!config('ledningssystemet.disable_archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\LibraryDocument':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);

               return ($user->hasAnyPermission(['managementtools.edit']) ||
                      ($user->id == $model->responsible_user_id));

            case 'Ledningssystemet\Ledningssystemet\Models\DocumentVersion':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);
               else
                  return ($user->hasAnyPermission(['managementtools.edit']) || ($model->int_library_document->responsible_user_id == auth()->user()->id));

            case 'Ledningssystemet\Ledningssystemet\Models\Role':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SubjectCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\DataCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\LegalBasis':
            case 'Ledningssystemet\Ledningssystemet\Models\RecipientCategory':
               return (!config('ledningssystemet.disable_gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SupplierCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\SupplierRequirement':
               return (!config('ledningssystemet.disable_supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityAspect':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\User':
            case 'Ledningssystemet\Ledningssystemet\Models\Site':
            case 'Ledningssystemet\Ledningssystemet\Models\AccessGroup':
            case 'Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Objective':
               if(is_string($model))
                  return $user->hasAnyPermission(['objectives.edit']);
               else
                  return (null == $model->archived_at) && $user->hasAnyPermission(['objectives.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Employee':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\EmployeeRole':
            case 'Ledningssystemet\Ledningssystemet\Models\Qualification':
            case 'Ledningssystemet\Ledningssystemet\Models\QualificationRole':
            case 'Ledningssystemet\Ledningssystemet\Models\RoleCompetence':
               return (!config('ledningssystemet.disable_staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\QualificationUser':
            case 'Ledningssystemet\Ledningssystemet\Models\CompetenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Competence':
            {
               if(config('ledningssystemet.disable_staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return true;

               if(('Ledningssystemet\Ledningssystemet\Models\Competence' == get_class($model)) && request()->has('user_id'))
                   return (\Ledningssystemet\Ledningssystemet\Models\User::where('id', request()->get('user_id'))->where('manager_user_id', $user->id)->exists());

               return ($model->user_id && \Ledningssystemet\Ledningssystemet\Models\User::where('id', $model->user_id)->where('manager_user_id', $user->id)->exists());
            }



            case 'Ledningssystemet\Ledningssystemet\Models\ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Chemical':
               return $user->hasAnyPermission(['chemicalregister.edit']);

            // Authorization handled by models themselves
            case 'Ledningssystemet\Ledningssystemet\Models\Activity':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlow':
               if (is_string($model))
                  return true;

               return (($model->responsible_user_id == $user->id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\ControlAction':
               if (is_string($model))
                  return true;

               return ($model->responsible_id == $user->id);

            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);


            case 'Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel':
               if (is_string($model))
                  return true;

               return (($model->user_id == $user->id) || $user->hasAnyPermission(['systemadministrator.edit']));

            case 'Ledningssystemet\Ledningssystemet\Models\File':
               if (is_string($model))
                  return true;

               return $user->can('update', $model->obj());

            case 'Ledningssystemet\Ledningssystemet\Models\Agreement':
               return $user->hasAnyPermission(['agreements.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\IgnoredRisk':
               return false;

            case 'Ledningssystemet\Ledningssystemet\Models\CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Form':
            case 'Ledningssystemet\Ledningssystemet\Models\FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\Relation':
               return $user->can('update', self::resolveObject($model));

            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactor':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactorReport':
               return $user->hasAnyPermission(['ghg.edit']);

         }
         return false;
      });

      // Delete
      Gate::define('delete', function (User $user, $model) {

         /* If a user can update, then it can delete for most objects */
         if (is_string($model)) {
            switch($model) {
               case 'Ledningssystemet\Ledningssystemet\Models\IgnoredRisk':
                  return $user->hasAnyPermission(['riskadministrator.edit']);
            }

            return $user->can('update', $model);
         }

         switch (is_string($model) ? $model : get_class($model)) {
            /* If a user can update, then it can delete for most objects */
            case 'Ledningssystemet\Ledningssystemet\Models\Customer':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluation':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirementFinding':
            case 'Ledningssystemet\Ledningssystemet\Models\ComplianceEvaluationRequirement':
            case 'Ledningssystemet\Ledningssystemet\Models\Requirement':
            case 'Ledningssystemet\Ledningssystemet\Models\RequirementSource':
            case 'Ledningssystemet\Ledningssystemet\Models\Process':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessActivity':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessHref':
            case 'Ledningssystemet\Ledningssystemet\Models\Supplier':
            case 'Ledningssystemet\Ledningssystemet\Models\Control':
            case 'Ledningssystemet\Ledningssystemet\Models\Incident':
            case 'Ledningssystemet\Ledningssystemet\Models\IncidentLog':
            case 'Ledningssystemet\Ledningssystemet\Models\Finding':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProject':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplateItem':
            case 'Ledningssystemet\Ledningssystemet\Models\AvailabilityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\ConsequenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Department':
            case 'Ledningssystemet\Ledningssystemet\Models\ProbabilityLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\Tag':
            case 'Ledningssystemet\Ledningssystemet\Models\Role':
            case 'Ledningssystemet\Ledningssystemet\Models\SubjectCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\DataCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\IntegrityClass':
            case 'Ledningssystemet\Ledningssystemet\Models\LegalBasis':
            case 'Ledningssystemet\Ledningssystemet\Models\RecipientCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\SupplierCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\SupplierRequirement':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityAspect':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\SustainabilityMetricLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\User':
            case 'Ledningssystemet\Ledningssystemet\Models\AccessGroup':
            case 'Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessPerformanceMetricReport':
            case 'Ledningssystemet\Ledningssystemet\Models\Objective':
            case 'Ledningssystemet\Ledningssystemet\Models\ObjectiveProcessPerformanceMetric':
            case 'Ledningssystemet\Ledningssystemet\Models\EmployeeRole':
            case 'Ledningssystemet\Ledningssystemet\Models\Qualification':
            case 'Ledningssystemet\Ledningssystemet\Models\QualificationRole':
            case 'Ledningssystemet\Ledningssystemet\Models\QualificationUser':
            case 'Ledningssystemet\Ledningssystemet\Models\RoleCompetence':
            case 'Ledningssystemet\Ledningssystemet\Models\Competence':
            case 'Ledningssystemet\Ledningssystemet\Models\CompetenceLevel':
            case 'Ledningssystemet\Ledningssystemet\Models\ProcessSustainabilityAspect':
            case 'Ledningssystemet\Ledningssystemet\Models\Chemical':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlow':
            case 'Ledningssystemet\Ledningssystemet\Models\Activity':
            case 'Ledningssystemet\Ledningssystemet\Models\UserNotificationChannel':
            case 'Ledningssystemet\Ledningssystemet\Models\ActivityFlowTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\ControlAction':
            case 'Ledningssystemet\Ledningssystemet\Models\Site':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectType':
            case 'Ledningssystemet\Ledningssystemet\Models\RiskProjectTypeRiskTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\Agreement':
            case 'Ledningssystemet\Ledningssystemet\Models\ConfidentialityGround':
            case 'Ledningssystemet\Ledningssystemet\Models\Diary':
            case 'Ledningssystemet\Ledningssystemet\Models\CustomProperty':
            case 'Ledningssystemet\Ledningssystemet\Models\FormTemplate':
            case 'Ledningssystemet\Ledningssystemet\Models\Form':
            case 'Ledningssystemet\Ledningssystemet\Models\FormRelation':
            case 'Ledningssystemet\Ledningssystemet\Models\Relation':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgCategory':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgConversionFactor':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactor':
            case 'Ledningssystemet\Ledningssystemet\Models\GhgFactorReport':
               return $user->can('update', $model);

            // Information types and assets that are connected to a process cannot be deleted
            case 'Ledningssystemet\Ledningssystemet\Models\InformationType':
               return ($user->can('update', $model) && !count($model->int_processes()));

            case 'Ledningssystemet\Ledningssystemet\Models\Asset':
               return ($user->can('update', $model) && !$model->int_processes()->count());

            case 'Ledningssystemet\Ledningssystemet\Models\Risk':
               if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may delete any risk
                  return true;

               return $user->can('update', $model);


            case 'Ledningssystemet\Ledningssystemet\Models\ObjectMessage':
               return $user->can('update', self::resolveObject($model));

            case 'Ledningssystemet\Ledningssystemet\Models\File':
               if (is_string($model))
                  return true;

               return $user->can('update', $model->obj());

            case 'Ledningssystemet\Ledningssystemet\Models\IgnoredRisk':
               return $user->hasAnyPermission(['riskadministrator.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\LibraryDocument':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Ledningssystemet\Ledningssystemet\Models\DocumentVersion':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);
               else
                  return !$model->approved_at && $user->can('update', $model);

         }

         return false;
      });
   }
}
