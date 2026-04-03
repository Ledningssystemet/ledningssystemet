<?php

use App\Http\Controllers\Api\GenericCrudController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/crud/{resource}', [GenericCrudController::class, 'index'])->name('api.crud.index');
    Route::post('/crud/{resource}', [GenericCrudController::class, 'store'])->name('api.crud.store');
    Route::get('/crud/{resource}/{id}', [GenericCrudController::class, 'show'])->name('api.crud.show');
    Route::match(['put', 'patch'], '/crud/{resource}/{id}', [GenericCrudController::class, 'update'])->name('api.crud.update');
    Route::delete('/crud/{resource}/{id}', [GenericCrudController::class, 'destroy'])->name('api.crud.destroy');
});

