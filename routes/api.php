<?php

use App\Http\Controllers\Api\AccessGroupOptionsController;
use App\Http\Controllers\Api\AdminApiTokenController;
use App\Http\Controllers\Api\CrudResourceCatalogController;
use App\Http\Controllers\Api\CustomPropertyContextController;
use App\Http\Controllers\Api\CustomPropertyCrudController;
use App\Http\Controllers\Api\GenericCrudController;
use App\Http\Controllers\Api\MenuBadgeController;
use App\Http\Controllers\Api\ProcessPublishController;
use App\Http\Controllers\Api\SessionStatusController;
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
    Route::get('/crud/{resource}', [GenericCrudController::class, 'index'])->name('api.crud.index');
    Route::post('/crud/{resource}', [GenericCrudController::class, 'store'])->name('api.crud.store');
    Route::get('/crud/{resource}/{id}', [GenericCrudController::class, 'show'])->name('api.crud.show');
    Route::match(['put', 'patch'], '/crud/{resource}/{id}', [GenericCrudController::class, 'update'])->name('api.crud.update');
    Route::delete('/crud/{resource}/{id}', [GenericCrudController::class, 'destroy'])->name('api.crud.destroy');

    // Process publishing with BPMN validation
    Route::post('/processes/{process}/publish', [ProcessPublishController::class, 'store'])->name('api.processes.publish');

    // Access Group options
    Route::get('/access-groups/claims', [AccessGroupOptionsController::class, 'claims'])
        ->name('api.access-groups.claims');
});
