<?php

use App\Http\Controllers\Api\GenericCrudController;
use App\Http\Controllers\Api\MenuBadgeController;
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

    // Generic CRUD
    Route::get('/crud/{resource}', [GenericCrudController::class, 'index'])->name('api.crud.index');
    Route::post('/crud/{resource}', [GenericCrudController::class, 'store'])->name('api.crud.store');
    Route::get('/crud/{resource}/{id}', [GenericCrudController::class, 'show'])->name('api.crud.show');
    Route::match(['put', 'patch'], '/crud/{resource}/{id}', [GenericCrudController::class, 'update'])->name('api.crud.update');
    Route::delete('/crud/{resource}/{id}', [GenericCrudController::class, 'destroy'])->name('api.crud.destroy');
});

