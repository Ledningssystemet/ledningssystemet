<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Resource map
    |--------------------------------------------------------------------------
    |
    | Optional explicit resource-to-model mapping. If empty, the endpoint tries
    | App\Models\{SingularStudly(resource)}.
    |
    */
    'resources' => [
        'agreements' => App\Models\Agreement::class,
        'activities' => App\Models\Activity::class,
        'activity-flows' => App\Models\ActivityFlow::class,
        'activity-flow-templates' => App\Models\ActivityFlowTemplate::class,
        'activity-flow-template-items' => App\Models\ActivityFlowTemplateItem::class,
        'assets' => App\Models\Asset::class,
        'asset-asset-dependancies' => App\Models\AssetAssetDependancy::class,
        'availability-classes' => App\Models\AvailabilityClass::class,
        'chemicals' => App\Models\Chemical::class,
        'confidentiality-classes' => App\Models\ConfidentialityClass::class,
        'confidentiality-grounds' => App\Models\ConfidentialityGround::class,
        'competences' => App\Models\Competence::class,
        'competence-levels' => App\Models\CompetenceLevel::class,
        'compliance_evaluations' => App\Models\ComplianceEvaluation::class,
        'compliance_evaluation_requirements' => App\Models\ComplianceEvaluationRequirement::class,
        'compliance_evaluation_requirement_sources' => App\Models\ComplianceEvaluationRequirementSource::class,
        'compliance_evaluation_requirement_findings' => App\Models\ComplianceEvaluationRequirementFinding::class,
        'consequence-levels' => App\Models\ConsequenceLevel::class,
        'controls' => App\Models\Control::class,
        'control_actions' => App\Models\ControlAction::class,
        'customers' => App\Models\Customer::class,
        'data-categories' => App\Models\DataCategory::class,
        'departments' => App\Models\Department::class,
        'diaries' => App\Models\Diary::class,
        'findings' => App\Models\Finding::class,
        'incidents' => App\Models\Incident::class,
        'incident-logs' => App\Models\IncidentLog::class,
        'control-action-mappings' => App\Models\ControlActionMapping::class,
        'information_types' => App\Models\InformationType::class,
        'integrity-classes' => App\Models\IntegrityClass::class,
        'legal-bases' => App\Models\LegalBasis::class,
        'library_documents' => App\Models\LibraryDocument::class,
        'objectives' => App\Models\Objective::class,
        'processes' => App\Models\Process::class,
        'process_sustainability_aspects' => App\Models\ProcessSustainabilityAspect::class,
        'process_activities' => App\Models\ProcessActivity::class,
        'process_performance_metrics' => App\Models\ProcessPerformanceMetric::class,
        'process_performance_metric_reports' => App\Models\ProcessPerformanceMetricReport::class,
        'probability-levels' => App\Models\ProbabilityLevel::class,
        'qualifications' => App\Models\Qualification::class,
        'qualification-users' => App\Models\QualificationUser::class,
        'qualification-roles' => App\Models\QualificationRole::class,
        'recipient-categories' => App\Models\RecipientCategory::class,
        'requirement_sources' => App\Models\RequirementSource::class,
        'risk-project-type-risk-templates' => App\Models\ProjectTypeRiskTemplate::class,
        'risk-project-types' => App\Models\ProjectType::class,
        'risk-level-mappings' => App\Models\RiskLevelMapping::class,
        'risk-levels' => App\Models\RiskLevel::class,
        'risks' => App\Models\Risk::class,
        'projects' => App\Models\Project::class,
        'roles' => App\Models\Role::class,
        'sites' => App\Models\Site::class,
        'subject-categories' => App\Models\SubjectCategory::class,
        'sustainability-aspects' => App\Models\SustainabilityAspect::class,
        'sustainability-metric-levels' => App\Models\SustainabilityMetricLevel::class,
        'sustainability-metrics' => App\Models\SustainabilityMetric::class,
        'suppliers' => App\Models\Supplier::class,
        'sustainability_aspects' => App\Models\SustainabilityAspect::class,
        'users' => App\Models\User::class,
    ],

    'default_paginate' => false,
    'default_per_page' => 25,
    'max_per_page' => 100,
];

