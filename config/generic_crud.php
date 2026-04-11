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
        'assets' => App\Models\Asset::class,
        'chemicals' => App\Models\Chemical::class,
        'competences' => App\Models\Competence::class,
        'compliance_evaluations' => App\Models\ComplianceEvaluation::class,
        'controls' => App\Models\Control::class,
        'control_actions' => App\Models\ControlAction::class,
        'customers' => App\Models\Customer::class,
        'departments' => App\Models\Department::class,
        'findings' => App\Models\Finding::class,
        'incidents' => App\Models\Incident::class,
        'information_types' => App\Models\InformationType::class,
        'library_documents' => App\Models\LibraryDocument::class,
        'objectives' => App\Models\Objective::class,
        'processes' => App\Models\Process::class,
        'process_activities' => App\Models\ProcessActivity::class,
        'process_performance_metrics' => App\Models\ProcessPerformanceMetric::class,
        'qualifications' => App\Models\Qualification::class,
        'requirement_sources' => App\Models\RequirementSource::class,
        'risks' => App\Models\Risk::class,
        'risk_projects' => App\Models\RiskProject::class,
        'roles' => App\Models\Role::class,
        'sites' => App\Models\Site::class,
        'suppliers' => App\Models\Supplier::class,
        'sustainability_aspects' => App\Models\SustainabilityAspect::class,
        'users' => App\Models\User::class,
    ],

    'default_paginate' => false,
    'default_per_page' => 25,
    'max_per_page' => 100,
];

