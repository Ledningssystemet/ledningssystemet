<?php

use App\Http\Controllers\Api\AccessGroupOptionsController;
use App\Http\Controllers\Api\AssessmentSettingsRiskMappingController;
use App\Http\Controllers\Api\ChemicalDatasheetDownloadController;
use App\Http\Controllers\Api\ComplianceEvaluationController;
use App\Http\Controllers\Api\EmployeeProfileController;
use App\Http\Controllers\Api\UserPasswordResetController;
use App\Http\Controllers\Api\UserReassignController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\AgreementArchiveController;
use App\Http\Controllers\Api\AdminApiTokenController;
use App\Http\Controllers\Api\DepartmentReassignController;
use App\Http\Controllers\Api\CrudResourceCatalogController;
use App\Http\Controllers\Api\CustomPropertyContextController;
use App\Http\Controllers\Api\CustomPropertyCrudController;
use App\Http\Controllers\Api\DocumentVersionActionController;
use App\Http\Controllers\Api\GenericCrudController;
use App\Http\Controllers\Api\LibraryDocumentController;
use App\Http\Controllers\Api\MenuBadgeController;
use App\Http\Controllers\Api\ObjectiveArchiveController;
use App\Http\Controllers\Api\ProcessPublishController;
use App\Http\Controllers\Api\SessionStatusController;
use App\Http\Controllers\Api\SupplierCrudSupportController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {

    // Navigation badges – works with both session (SPA) and Bearer token (external)
    Route::get('/menu/badges', [MenuBadgeController::class, 'index'])->name('api.menu.badges');
    Route::get('/session/ping', [SessionStatusController::class, 'show'])
        ->middleware('session.authenticated')
        ->name('api.session.ping');

    // Personal Access Tokens (scoped to authenticated user)
    Route::get('/tokens', [TokenController::class, 'index'])->name('api.tokens.index');
    Route::post('/tokens', [TokenController::class, 'store'])
        ->middleware('session.authenticated')
        ->name('api.tokens.store');
    Route::delete('/tokens/current', [TokenController::class, 'destroyCurrent'])->name('api.tokens.destroy-current');
    Route::delete('/tokens/{tokenId}', [TokenController::class, 'destroy'])->name('api.tokens.destroy');

    // Admin Personal Access Tokens (legacy parity)
    Route::get('/admin/api-tokens', [AdminApiTokenController::class, 'index'])->name('api.admin.api-tokens.index');
    Route::post('/admin/api-tokens', [AdminApiTokenController::class, 'store'])
        ->middleware('session.authenticated')
        ->name('api.admin.api-tokens.store');
    Route::match(['put', 'patch'], '/admin/api-tokens/{tokenId}', [AdminApiTokenController::class, 'update'])
        ->name('api.admin.api-tokens.update');
    Route::delete('/admin/api-tokens/{tokenId}', [AdminApiTokenController::class, 'destroy'])
        ->name('api.admin.api-tokens.destroy');

    // Generic CRUD
    Route::get('/crud/resources', [CrudResourceCatalogController::class, 'index'])->name('api.crud.resources');
    Route::get('/custom-properties/contexts', [CustomPropertyContextController::class, 'index'])
        ->name('api.custom-properties.contexts');
    Route::get('/custom-properties/contexts/{context}', [CustomPropertyCrudController::class, 'index'])
        ->name('api.custom-properties.index');
    Route::post('/custom-properties/contexts/{context}', [CustomPropertyCrudController::class, 'store'])
        ->name('api.custom-properties.store');
    Route::match(['put', 'patch'], '/custom-properties/contexts/{context}/{id}', [CustomPropertyCrudController::class, 'update'])
        ->name('api.custom-properties.update');
    Route::delete('/custom-properties/contexts/{context}/{id}', [CustomPropertyCrudController::class, 'destroy'])
        ->name('api.custom-properties.destroy');

    // Library Documents - Custom controller for dual-mode support
    Route::post('/crud/library_documents', [LibraryDocumentController::class, 'store'])->name('api.crud.library_documents.store');

    // Generic CRUD routes
    Route::get('/crud/{resource}', [GenericCrudController::class, 'index'])->name('api.crud.index');
    Route::post('/crud/{resource}', [GenericCrudController::class, 'store'])->name('api.crud.store');
    Route::get('/crud/{resource}/{id}', [GenericCrudController::class, 'show'])->name('api.crud.show');
    Route::match(['put', 'patch'], '/crud/{resource}/{id}', [GenericCrudController::class, 'update'])->name('api.crud.update');
    Route::delete('/crud/{resource}/{id}', [GenericCrudController::class, 'destroy'])->name('api.crud.destroy');
    Route::get('/v1/items/Chemical/{id}/download', [ChemicalDatasheetDownloadController::class, 'show'])
        ->name('api.chemicals.datasheet.download');
    Route::post('/departments/{department}/reassign', [DepartmentReassignController::class, 'store'])
        ->name('api.departments.reassign');
    Route::post('/users/{user}/reassign', [UserReassignController::class, 'store'])
        ->name('api.users.reassign');
    Route::post('/users/{user}/password-reset', [UserPasswordResetController::class, 'store'])
        ->name('api.users.password-reset');
    Route::get('/assessment-settings/risk-mappings', [AssessmentSettingsRiskMappingController::class, 'index'])
        ->name('api.assessment-settings.risk-mappings.index');
    Route::post('/assessment-settings/risk-mappings', [AssessmentSettingsRiskMappingController::class, 'store'])
        ->name('api.assessment-settings.risk-mappings.store');
    Route::post('/agreements/{agreement}/archive', AgreementArchiveController::class)->name('api.agreements.archive');
    Route::post('/objectives/{objective}/archive', ObjectiveArchiveController::class)->name('api.objectives.archive');

    Route::get('/suppliers/category-options', [SupplierCrudSupportController::class, 'categoryOptions'])
        ->name('api.suppliers.category-options');
    Route::get('/suppliers/{supplier}/categories', [SupplierCrudSupportController::class, 'categories'])
        ->name('api.suppliers.categories.index');
    Route::match(['put', 'patch'], '/suppliers/{supplier}/categories/{category}', [SupplierCrudSupportController::class, 'updateCategory'])
        ->name('api.suppliers.categories.update');
    Route::get('/suppliers/{supplier}/evaluation', [SupplierCrudSupportController::class, 'evaluation'])
        ->name('api.suppliers.evaluation.index');
    Route::match(['put', 'patch'], '/suppliers/{supplier}/evaluation/{requirement}', [SupplierCrudSupportController::class, 'updateEvaluation'])
        ->name('api.suppliers.evaluation.update');

    // Process publishing with BPMN validation
    Route::post('/processes/{process}/publish', [ProcessPublishController::class, 'store'])->name('api.processes.publish');

    // Compliance evaluations
    Route::get('/compliance-evaluations/{evaluation}', [ComplianceEvaluationController::class, 'show'])->name('api.compliance-evaluations.show');
    Route::get('/compliance-evaluations/{evaluation}/requirement-sources', [ComplianceEvaluationController::class, 'requirementSources'])->name('api.compliance-evaluations.requirement-sources');
    Route::post('/compliance-evaluations/{evaluation}/generate', [ComplianceEvaluationController::class, 'generateChecklist'])->name('api.compliance-evaluations.generate');
    Route::post('/compliance-evaluations/{evaluation}/finish', [ComplianceEvaluationController::class, 'finish'])->name('api.compliance-evaluations.finish');
    Route::post('/compliance-evaluations/{evaluation}/reopen', [ComplianceEvaluationController::class, 'reopen'])->name('api.compliance-evaluations.reopen');
    Route::post('/compliance-evaluations/{evaluation}/archive', [ComplianceEvaluationController::class, 'archive'])->name('api.compliance-evaluations.archive');

    // Document version actions
    Route::post('/document-versions/{versionId}/finish', [DocumentVersionActionController::class, 'finish'])->name('api.document-versions.finish');
    Route::post('/document-versions/{versionId}/approve', [DocumentVersionActionController::class, 'approve'])->name('api.document-versions.approve');
    Route::post('/document-versions/{versionId}/reject', [DocumentVersionActionController::class, 'reject'])->name('api.document-versions.reject');

    // ...existing code...
    Route::get('/access-groups/claims', [AccessGroupOptionsController::class, 'claims'])
        ->name('api.access-groups.claims');

    // My profile
    Route::get('/my-profile', [UserProfileController::class, 'show'])->name('api.my-profile.show');
    Route::get('/my-profile/roles', [UserProfileController::class, 'roles'])->name('api.my-profile.roles');
    Route::get('/my-profile/qualifications', [UserProfileController::class, 'qualifications'])->name('api.my-profile.qualifications');
    Route::get('/my-profile/competences', [UserProfileController::class, 'competences'])->name('api.my-profile.competences');
    Route::get('/my-profile/responsibilities', [UserProfileController::class, 'responsibilities'])->name('api.my-profile.responsibilities');

    // Employee profiles (overview of any employee's roles, responsibilities and competences)
    Route::get('/employees/{userId}', [EmployeeProfileController::class, 'show'])->name('api.employees.show');
    Route::get('/employees/{userId}/roles', [EmployeeProfileController::class, 'roles'])->name('api.employees.roles');
    Route::get('/employees/{userId}/qualifications', [EmployeeProfileController::class, 'qualifications'])->name('api.employees.qualifications');
    Route::get('/employees/{userId}/competences', [EmployeeProfileController::class, 'competences'])->name('api.employees.competences');
    Route::get('/employees/{userId}/responsibilities', [EmployeeProfileController::class, 'responsibilities'])->name('api.employees.responsibilities');
});
