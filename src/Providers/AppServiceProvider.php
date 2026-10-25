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
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;
use Ledningssystemet\Ledningssystemet\Support\Modules;
use Ledningssystemet\Ledningssystemet\Support\SystemSettings;

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
      $this->app->register(PennantServiceProvider::class);
      config(['pennant.stores.ledningssystemet' => [
         'driver' => 'database',
         'connection' => null,
         'table' => 'system_settings',
      ]]);
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
      foreach (array_keys(Modules::LABELS) as $module) {
         Feature::store('ledningssystemet')->define($module, function () use ($module): never {
            throw new \LogicException("Missing module setting [{$module}]. Run database migrations.");
         });
      }

      Gate::define('manage-system-settings', fn (User $user) => $user->hasPermissionTo('systemadministrator.edit'));
      foreach (array_keys(SystemSettings::CONTACT_FIELDS) as $name) {
         Feature::store('ledningssystemet')->define($name, function () use ($name): never {
            throw new \LogicException("Missing system setting [{$name}]. Run database migrations.");
         });
      }

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

      Gate::define('publishdocument', function (User $user, $document) {
         if(is_string($document))
            return $user->hasAnyPermission(['documentpublisher.edit']);
         else
            return $user->hasAnyPermission(['documentpublisher.edit']) && ($document->approver_id == $user->id);
      });

      /* Define access levels for model actions */
      // Indexing (list all)
      Gate::define('index', function (User $user, $model) {
         $modelClass = is_string($model) ? $model : get_class($model);
         
         // Remove any namespace declaration from the modelClass
         if(str_contains($modelClass, '\\')) {
            $modelClass = substr($modelClass, strrpos($modelClass, '\\') + 1);
         }
         
         switch ($modelClass) {
            case 'Customer':
               return $user->hasAnyPermission(['customers.read', 'customers.edit']);

            case 'ComplianceEvaluation':
            case 'ComplianceEvaluationRequirementFinding':
            case 'ComplianceEvaluationRequirement':
            case 'ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.read', 'complianceevaluations.edit']);

            case 'Requirement':
            case 'RequirementSource':
               return $user->hasAnyPermission(['requirements.read', 'requirements.edit']);

            case 'Process':
            case 'ProcessActivity':
            case 'ProcessHref':
            case 'InformationType':
            case 'Asset':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'Supplier':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['suppliers.read', 'suppliers.edit']);

            case 'Control':
               return $user->hasAnyPermission(['controls.read', 'controls.edit']);

            case 'Risk':
               return $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']);

            case 'Finding':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('finding')) && $user->hasAnyPermission(['findings.read', 'findings.edit']);

            case 'Incident':
            case 'IncidentLog':
               return $user->hasAnyPermission(['incidents.read', 'incidents.edit']);

            case 'ActivityFlowTemplateItem':
            case 'AvailabilityClass':
            case 'ConfidentialityClass':
            case 'IntegrityClass':
            case 'ConsequenceLevel':
            case 'Department':
            case 'FormTemplate':
            case 'ProbabilityLevel':
            case 'RiskLevel':
            case 'Tag':
            case 'ProjectType':
            case 'ProjectTypeRiskTemplate':
            case 'ProjectMeetingAgendaTemplate':
            case 'ProjectMeetingAgendaTemplateItem':
            case 'GhgCategory':
            case 'GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'ConfidentialityGround':
            case 'Diary':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'LibraryDocument':
            case 'DocumentVersion':
               // Authorization handled by models themselves
            return true;

            case 'Role':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SubjectCategory':
            case 'DataCategory':
            case 'LegalBasis':
            case 'RecipientCategory':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SupplierCategory':
            case 'SupplierRequirement':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.read', 'suppliers.edit']);

            case 'SustainabilityAspect':
            case 'SustainabilityMetric':
            case 'SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'User':
            case 'Site':
            case 'AccessGroup':
            case 'PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'ProcessPerformanceMetric':
            case 'ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.read', 'processmetrics.edit']);

            case 'Objective':
            case 'ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.read', 'objectives.edit']);


            case 'Employee':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'EmployeeRole':
            case 'Qualification':
            case 'QualificationRole':
            case 'RoleCompetence':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'QualificationUser':
            case 'UserCompetence':
            {
               if(\Ledningssystemet\Ledningssystemet\Support\Modules::inactive('staff')) return false;

               if(is_string($model) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']))
                  return true;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               return ($model->manager_user_id == $user->id);
            }

            case 'ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.read', 'sustainabilityaspects.edit']);

            case 'Chemical':
               return $user->hasAnyPermission(['chemicalregister.read', 'chemicalregister.edit']);

            case 'Agreement':
               return $user->hasAnyPermission(['agreements.read', 'agreements.edit']);

            case 'CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'GhgFactor':
            case 'GhgFactorReport':
               return $user->hasAnyPermission(['ghg.read', 'ghg.edit']);

            // Authorization handled by models themselves
            case 'Activity':
            case 'ActivityFlow':
            case 'ActivityFlowTemplate':
            case 'UserNotificationChannel':
            case 'ActivityLog':
            case 'ObjectMessage':
            case 'Me':
            case 'Competence':
            case 'CompetenceLevel':
            case 'ControlAction':
            case 'File':
            case 'Relation':
            case 'Form':
               return true;
         }

         return false;
      });

      // View (list single)
      Gate::define('view', function (User $user, $model) {
         $modelClass = is_string($model) ? $model : get_class($model);

         // Remove any namespace declaration from the modelClass
         if(str_contains($modelClass, '\\')) {
            $modelClass = substr($modelClass, strrpos($modelClass, '\\') + 1);
         }

         switch ($modelClass) {
            case 'Customer':
               return $user->hasAnyPermission(['customers.read', 'customers.edit']);

            case 'Me':
            case 'Competence':
            case 'CompetenceLevel':
               return true;

            case 'ActivityLog':
            case 'ObjectMessage':
               return $user->can('view', self::resolveObject($model));


            case 'ComplianceEvaluation':
            case 'ComplianceEvaluationRequirementFinding':
            case 'ComplianceEvaluationRequirement':
            case 'ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.read', 'complianceevaluations.edit']);

            case 'Requirement':
            case 'RequirementSource':
               return $user->hasAnyPermission(['requirements.read', 'requirements.edit']);


            case 'Process':
            case 'ProcessActivity':
            case 'ProcessHref':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'InformationType':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'Asset':
               return $user->hasAnyPermission(['processes.read', 'processes.edit']);

            case 'Supplier':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['suppliers.read', 'suppliers.edit']);

            case 'Control':
               return $user->hasAnyPermission(['controls.read', 'controls.edit']);

            case 'Risk':

               if (is_string($model))
                  return $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']);

               if ($user->hasAnyPermission(['riskadministrator.edit', 'riskall.edit']))
                  return true;
               if ((null != $model->riskowner_id) && ($model->riskowner_id == $user->id))
                  return true;
               if ($user->hasAnyPermission(['riskdepartment.edit']) && ($user->int_departments()->where('departments.id', $model->department_id)->exists()))
                  return true;

               return false;

            case 'Finding':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('finding')) && $user->hasAnyPermission(['findings.read', 'findings.edit']);

            case 'Incident':
            case 'IncidentLog':
               return $user->hasAnyPermission(['incidents.read', 'incidents.edit']);


            case 'ActivityFlowTemplate':
            case 'ActivityFlowTemplateItem':
            case 'AvailabilityClass':
            case 'ConfidentialityClass':
            case 'IntegrityClass':
            case 'ConsequenceLevel':
            case 'Department':
            case 'ProbabilityLevel':
            case 'RiskLevel':
            case 'Tag':
            case 'ProjectType':
            case 'ProjectTypeRiskTemplate':
            case 'ProjectMeetingAgendaTemplate':
            case 'ProjectMeetingAgendaTemplateItem':
            case 'GhgCategory':
            case 'GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'ConfidentialityGround':
            case 'Diary':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'LibraryDocument':
               return true;

            case 'DocumentVersion':
               if(is_string($model))
                  return false;
               else
                  return (($user->id == $model->approver_id) || ($user->id == $model->int_library_document->responsible_user_id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'Role':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SubjectCategory':
            case 'DataCategory':
            case 'LegalBasis':
            case 'RecipientCategory':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SupplierCategory':
            case 'SupplierRequirement':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.read', 'suppliers.edit']);

            case 'SustainabilityAspect':
            case 'SustainabilityMetric':
            case 'SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'User':
            case 'Site':
            case 'AccessGroup':
            case 'PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'ProcessPerformanceMetric':
            case 'ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.read', 'processmetrics.edit']);

            case 'Objective':
            case 'ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.read', 'objectives.edit']);


            case 'Employee':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'EmployeeRole':
            case 'Qualification':
            case 'QualificationRole':
            case 'RoleCompetence':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'QualificationUser':
            case 'UserCompetence':
            {
               if(\Ledningssystemet\Ledningssystemet\Support\Modules::inactive('staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return true;

               return ($model->int_user->manager_user_id == $user->id);
            }


            case 'ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.read', 'sustainabilityaspects.edit']);

            case 'Chemical':
               return $user->hasAnyPermission(['chemicalregister.read', 'chemicalregister.edit']);

            case 'Activity':
            case 'ActivityFlow':
               if ('Activity' === $modelClass && $model->project_id) {
                  $project = $model->int_project;
                  return $project && $user->can('view', $project);
               }

               return (($model->responsible_user_id == $user->id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'UserNotificationChannel':
               return (($model->user_id == $user->id) || $user->hasAnyPermission(['systemadministrator.edit']));

            case 'ControlAction':
               return (($model->responsible_id == $user->id) || $user->hasAnyPermission(['allcontrolactions.read']));

            case 'File':
               return $user->can('view', self::resolveObject($model));

            case 'Agreement':
               return $user->hasAnyPermission(['agreements.read', 'agreements.edit']);

            case 'CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'FormTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Form':
               return $user->hasAnyPermission(['forms.edit']) || $user->can('view', self::resolveObject($model));

            case 'FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'Relation':
               if(is_string($model))
                  return true;

               return $user->can('view', $model->int_relation);

            case 'GhgFactor':
            case 'GhgFactorReport':
               return $user->hasAnyPermission(['ghg.read', 'ghg.edit']);

         }

         return false;
      });

      // Create
      Gate::define('create', function (User $user, $model) {
         $modelClass = is_string($model) ? $model : get_class($model);

         // Remove any namespace declaration from the modelClass
         if(str_contains($modelClass, '\\')) {
            $modelClass = substr($modelClass, strrpos($modelClass, '\\') + 1);
         }

         switch ($modelClass) {
            case 'Customer':
               return $user->hasAnyPermission(['customers.edit']);

            case 'ComplianceEvaluation':
            case 'ComplianceEvaluationRequirementFinding':
            case 'ComplianceEvaluationRequirement':
            case 'ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.edit']);

            case 'Requirement':
            case 'RequirementSource':
               return $user->hasAnyPermission(['requirements.edit']);

            case 'Process':
            case 'ProcessActivity':
            case 'ProcessHref':
               return $user->hasAnyPermission(['processes.edit']);

            case 'InformationType':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Asset':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Supplier':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['suppliers.edit']);

            case 'Control':
               return $user->hasAnyPermission(['controls.edit']);

            case 'Risk':
               return true;

            case 'Finding':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('finding'));

            case 'Incident':
            case 'IncidentLog':
               return $user->hasAnyPermission(['incidents.edit']);

            case 'ActivityFlowTemplateItem':
            case 'AvailabilityClass':
            case 'ConfidentialityClass':
            case 'IntegrityClass':
            case 'ConsequenceLevel':
            case 'Department':
            case 'ProbabilityLevel':
            case 'RiskLevel':
            case 'Tag':
            case 'ProjectType':
            case 'ProjectTypeRiskTemplate':
            case 'ProjectMeetingAgendaTemplate':
            case 'ProjectMeetingAgendaTemplateItem':
            case 'FormTemplate':
            case 'GhgCategory':
            case 'GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'ConfidentialityGround':
            case 'Diary':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'LibraryDocument':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Role':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SubjectCategory':
            case 'DataCategory':
            case 'LegalBasis':
            case 'RecipientCategory':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SupplierCategory':
            case 'SupplierRequirement':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SustainabilityAspect':
            case 'SustainabilityMetric':
            case 'SustainabilityMetricLevel':
            case 'ActivityFlowTemplate':

               return $user->hasAnyPermission(['managementtools.edit']);

            case 'User':
            case 'Site':
            case 'AccessGroup':
            case 'PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'ProcessPerformanceMetric':
            case 'ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.edit']);

            case 'Objective':
            case 'ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.edit']);


            case 'Employee':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'EmployeeRole':
            case 'Qualification':
            case 'QualificationRole':
            case 'RoleCompetence':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'QualificationUser':
            case 'Competence':
            case 'CompetenceLevel':
            {
               if(\Ledningssystemet\Ledningssystemet\Support\Modules::inactive('staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return ('Competence' != $model);

               return ($model->user_id && \Ledningssystemet\Ledningssystemet\Models\User::where('id', $model->user_id)->where('manager_user_id', $user->id)->exists());
            }

            case 'ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.edit']);

            case 'Chemical':
               return $user->hasAnyPermission(['chemicalregister.edit']);

            case 'Activity':
               $projectId = intval(request()->input('project_id', 0));
               if ($projectId <= 0)
                  return true;

               $project = \Ledningssystemet\Ledningssystemet\Models\Project::find($projectId);
               return $project && $user->can('view', $project);

            case 'ActivityFlow':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'Agreement':
               return $user->hasAnyPermission(['agreements.edit']);

            case 'CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'UserNotificationChannel':
            case 'ObjectMessage':
            case 'ControlAction':
            case 'File':
            case 'Relation':
               return true;

            case 'Form':
            case 'FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'GhgFactor':
            case 'GhgFactorReport':
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

         $modelClass = is_string($model) ? $model : get_class($model);

         // Remove any namespace declaration from the modelClass
         if(str_contains($modelClass, '\\')) {
            $modelClass = substr($modelClass, strrpos($modelClass, '\\') + 1);
         }

         switch ($modelClass) {
            case 'Customer':
               return $user->hasAnyPermission(['customers.edit']);

            case 'ComplianceEvaluation':
            case 'ComplianceEvaluationRequirementFinding':
            case 'ComplianceEvaluationRequirement':
            case 'ComplianceEvaluationRequirementSource':
               return $user->hasAnyPermission(['complianceevaluations.edit']);

            case 'Requirement':
            case 'RequirementSource':
               return $user->hasAnyPermission(['requirements.edit']);

            case 'Process':
            case 'ProcessActivity':
            case 'ProcessHref':
               return $user->hasAnyPermission(['processes.edit']);

            case 'InformationType':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Asset':
               return $user->hasAnyPermission(['processes.edit']);

            case 'Supplier':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['suppliers.edit']);

            case 'Control':
               return $user->hasAnyPermission(['controls.edit']);


            case 'Risk':
               if (is_string($model) && $user->hasAnyPermission(['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']))
                  return true;

               else if(is_string($model))
                  return false;

               if ($user->hasAnyPermission(['riskadministrator.edit', 'riskall.edit']))
                  return true;
               if ((null != $model->riskowner_id) && ($model->riskowner_id == $user->id))
                  return true;
               if ($user->hasAnyPermission(['riskdepartment.edit']) && ($user->int_departments()->where('departments.id', $model->department_id)->exists()))
                  return true;
               return false;

            case 'Finding':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('finding')) && $user->hasAnyPermission(['findings.edit']);

            case 'Incident':
            case 'IncidentLog':
               return $user->hasAnyPermission(['incidents.edit']);

            case 'ActivityFlowTemplateItem':
            case 'AvailabilityClass':
            case 'ConfidentialityClass':
            case 'IntegrityClass':
            case 'ConsequenceLevel':
            case 'Department':
            case 'ProbabilityLevel':
            case 'RiskLevel':
            case 'Tag':
            case 'ProjectType':
            case 'ProjectTypeRiskTemplate':
            case 'ProjectMeetingAgendaTemplate':
            case 'ProjectMeetingAgendaTemplateItem':
            case 'FormTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'GhgCategory':
            case 'GhgConversionFactor':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'ConfidentialityGround':
            case 'Diary':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('archival') && $user->hasAnyPermission(['managementtools.edit']));

            case 'LibraryDocument':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);

               return ($user->hasAnyPermission(['managementtools.edit']) ||
                      ($user->id == $model->responsible_user_id));

            case 'DocumentVersion':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);
               else
                  return ($user->hasAnyPermission(['managementtools.edit']) || ($model->int_library_document->responsible_user_id == auth()->user()->id));

            case 'Role':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SubjectCategory':
            case 'DataCategory':
            case 'LegalBasis':
            case 'RecipientCategory':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('gdpr')) && $user->hasAnyPermission(['managementtools.edit']);

            case 'SupplierCategory':
            case 'SupplierRequirement':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('supplier')) && $user->hasAnyPermission(['managementtools.edit', 'suppliers.edit']);

            case 'SustainabilityAspect':
            case 'SustainabilityMetric':
            case 'SustainabilityMetricLevel':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'User':
            case 'Site':
            case 'AccessGroup':
            case 'PersonalAccessToken':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'ProcessPerformanceMetric':
            case 'ProcessPerformanceMetricReport':
               return $user->hasAnyPermission(['processmetrics.edit']);

            case 'Objective':
               if(is_string($model))
                  return $user->hasAnyPermission(['objectives.edit']);
               else
                  return (null == $model->archived_at) && $user->hasAnyPermission(['objectives.edit']);

            case 'ObjectiveProcessPerformanceMetric':
               return $user->hasAnyPermission(['objectives.edit']);

            case 'Employee':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit', 'subordinateemployeemenagement.edit']);

            case 'EmployeeRole':
            case 'Qualification':
            case 'QualificationRole':
            case 'RoleCompetence':
               return (\Ledningssystemet\Ledningssystemet\Support\Modules::active('staff')) && $user->hasAnyPermission(['employeemanagement.edit']);

            case 'QualificationUser':
            case 'CompetenceLevel':
            case 'Competence':
            {
               if(\Ledningssystemet\Ledningssystemet\Support\Modules::inactive('staff')) return false;

               if($user->hasAnyPermission(['employeemanagement.edit']))
                  return true;

               if(!$user->hasAnyPermission(['subordinateemployeemenagement.edit']))
                  return false;

               if(is_string($model))
                  return true;

               if(('Competence' == get_class($model)) && request()->has('user_id'))
                   return (\Ledningssystemet\Ledningssystemet\Models\User::where('id', request()->get('user_id'))->where('manager_user_id', $user->id)->exists());

               return ($model->user_id && \Ledningssystemet\Ledningssystemet\Models\User::where('id', $model->user_id)->where('manager_user_id', $user->id)->exists());
            }



            case 'ProcessSustainabilityAspect':
               return $user->hasAnyPermission(['sustainabilityaspects.edit']);

            case 'Chemical':
               return $user->hasAnyPermission(['chemicalregister.edit']);

            // Authorization handled by models themselves
            case 'Activity':
            case 'ActivityFlow':
               if (is_string($model))
                  return true;

               if ('Activity' === $modelClass && $model->project_id) {
                  $project = $model->int_project;
                  return $project && (
                     $user->can('update', $project)
                     || $model->responsible_user_id == $user->id
                  );
               }

               return (($model->responsible_user_id == $user->id) || $user->hasAnyPermission(['managementtools.edit']));

            case 'ControlAction':
               if (is_string($model))
                  return true;

               return ($model->responsible_id == $user->id);

            case 'ActivityFlowTemplate':
               return $user->hasAnyPermission(['managementtools.edit']);


            case 'UserNotificationChannel':
               if (is_string($model))
                  return true;

               return (($model->user_id == $user->id) || $user->hasAnyPermission(['systemadministrator.edit']));

            case 'File':
               if (is_string($model))
                  return true;

               return $user->can('update', $model->obj());

            case 'Agreement':
               return $user->hasAnyPermission(['agreements.edit']);

            case 'CustomProperty':
               return $user->hasAnyPermission(['systemadministrator.edit']);

            case 'Form':
            case 'FormRelation':
               return $user->hasAnyPermission(['forms.edit']);

            case 'Relation':
               return $user->can('update', $model->int_relation);

            case 'GhgFactor':
            case 'GhgFactorReport':
               return $user->hasAnyPermission(['ghg.edit']);

         }
         return false;
      });

      // Delete
      Gate::define('delete', function (User $user, $model) {

         /* If a user can update, then it can delete for most objects */
         if (is_string($model)) {
            return $user->can('update', $model);
         }

         $modelClass = is_string($model) ? $model : get_class($model);

         // Remove any namespace declaration from the modelClass
         if(str_contains($modelClass, '\\')) {
            $modelClass = substr($modelClass, strrpos($modelClass, '\\') + 1);
         }

         switch ($modelClass) {
            /* If a user can update, then it can delete for most objects */
            case 'Customer':
            case 'ComplianceEvaluation':
            case 'ComplianceEvaluationRequirementFinding':
            case 'ComplianceEvaluationRequirement':
            case 'Requirement':
            case 'RequirementSource':
            case 'Process':
            case 'ProcessActivity':
            case 'ProcessHref':
            case 'Supplier':
            case 'Control':
            case 'Incident':
            case 'IncidentLog':
            case 'Finding':
            case 'ActivityFlowTemplateItem':
            case 'AvailabilityClass':
            case 'ConfidentialityClass':
            case 'ConsequenceLevel':
            case 'Department':
            case 'ProbabilityLevel':
            case 'RiskLevel':
            case 'Tag':
            case 'Role':
            case 'SubjectCategory':
            case 'DataCategory':
            case 'IntegrityClass':
            case 'LegalBasis':
            case 'RecipientCategory':
            case 'SupplierCategory':
            case 'SupplierRequirement':
            case 'SustainabilityAspect':
            case 'SustainabilityMetric':
            case 'SustainabilityMetricLevel':
            case 'User':
            case 'AccessGroup':
            case 'PersonalAccessToken':
            case 'ProcessPerformanceMetric':
            case 'ProcessPerformanceMetricReport':
            case 'Objective':
            case 'ObjectiveProcessPerformanceMetric':
            case 'EmployeeRole':
            case 'Qualification':
            case 'QualificationRole':
            case 'QualificationUser':
            case 'RoleCompetence':
            case 'Competence':
            case 'CompetenceLevel':
            case 'ProcessSustainabilityAspect':
            case 'Chemical':
            case 'ActivityFlow':
            case 'Activity':
            case 'UserNotificationChannel':
            case 'ActivityFlowTemplate':
            case 'ControlAction':
            case 'Site':
            case 'ProjectType':
            case 'ProjectTypeRiskTemplate':
            case 'ProjectMeetingAgendaTemplate':
            case 'ProjectMeetingAgendaTemplateItem':
            case 'Agreement':
            case 'ConfidentialityGround':
            case 'Diary':
            case 'CustomProperty':
            case 'FormTemplate':
            case 'Form':
            case 'FormRelation':
            case 'Relation':
            case 'GhgCategory':
            case 'GhgConversionFactor':
            case 'GhgFactor':
            case 'GhgFactorReport':
               return $user->can('update', $model);

            // Information types and assets that are connected to a process cannot be deleted
            case 'InformationType':
               return ($user->can('update', $model) && !count($model->int_processes()));

            case 'Asset':
               return ($user->can('update', $model) && !$model->int_processes()->count());

            case 'Risk':
               if ($user->hasAnyPermission(['riskadministrator.edit'])) // If user is admin, then user may delete any risk
                  return true;

               return $user->can('update', $model);

            case 'ObjectMessage':
               return $user->can('update', self::resolveObject($model));

            case 'File':
               if (is_string($model))
                  return true;

               return $user->can('update', $model->obj());

            case 'LibraryDocument':
               return $user->hasAnyPermission(['managementtools.edit']);

            case 'DocumentVersion':
               if(is_string($model))
                  return $user->hasAnyPermission(['managementtools.edit']);
               else
                  return !$model->approved_at && $user->can('update', $model);

         }

         return false;
      });
   }
}
