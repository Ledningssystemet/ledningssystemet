<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpChallengeController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\OpenApiDocumentationController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\PluginAssetController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/'.trim((string) config('plugins.asset_route_prefix', 'plugin-assets'), '/').'/{plugin}/{path}', PluginAssetController::class)
    ->where('path', '.*')
    ->name('plugins.asset');

Route::get('/openapi.json', [OpenApiDocumentationController::class, 'spec'])->name('openapi.spec');
Route::get('/api/docs', [OpenApiDocumentationController::class, 'show'])->name('openapi.docs');

Route::middleware('auth')->group(function (): void {
    /**
     * Logout
     */
    Route::match(['get', 'post'], '/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('home');
    });

    Route::get('/app/{path?}', function () {
        return Inertia::render('AppShell');
    })->where('path', '.*')->name('home');


});


Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

    Route::get('/otp/challenge', [OtpChallengeController::class, 'create'])->name('otp.challenge');
    Route::post('/otp/challenge', [OtpChallengeController::class, 'store'])->name('otp.verify');
    Route::post('/otp/resend', [OtpChallengeController::class, 'resend'])->name('otp.resend');

    Route::prefix('oauth')->name('oauth.')->group(function (): void {
        Route::get('workplace/redirect', [OAuthController::class, 'redirect'])->name('redirect');
        Route::get('workplace/callback', [OAuthController::class, 'callback'])->name('callback');
    });

    // Redirect /oauthcallback to the workplace callback with preserved query parameters
    Route::get('/oauthcallback', function () {
        // Extract the query parameters from the request and append them to the redirect URL
        $queryParams = http_build_query(request()->query());
        $redirectUrl = '/oauth/workplace/callback?'.$queryParams;
        return redirect()->to($redirectUrl, 301);
    })->name('oauthcallback');
});
