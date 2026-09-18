<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class LoginController extends Controller
{
   /**
    * Display login page.
    */
   public function show()
   {
      if(config('ledningssystemet.login_allow_passwordlogin') || SsoController::hasEnabledProviders()) {
         $ssoProviders = collect(SsoController::enabledProviders())->map(function (array $provider): array {
            return [
               'name' => $provider['name'],
               'label' => $provider['label'],
               'url' => route('sso.redirect', ['provider' => $provider['name']]),
               'buttonText' => __('Continue with :provider', ['provider' => $provider['label']]),
            ];
         })->values()->all();

         return view('auth.react-shell', ['props' => [
            'screen' => 'login',
            'pageClass' => 'login',
            'formAction' => route('login'),
            'passwordResetRequestAction' => route('password.email'),
            'passwordLoginEnabled' => (bool) config('ledningssystemet.login_allow_passwordlogin'),
            'mfaEnabled' => (bool) config('ledningssystemet.mfa_enabled'),
            'ssoProviders' => $ssoProviders,
            'logoSrc' => file_exists(resource_path().'/brand/logo/logo_login.png')
               ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_login.png'))
               : '/images/logo_'.app()->getLocale().'_white.png',
            'passwordResetLogoSrc' => file_exists(resource_path().'/brand/logo/logo_passwordreset.png')
               ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_passwordreset.png'))
               : '/images/logo_'.app()->getLocale().'_white.png',
            'keepAliveUrl' => route('auth.keepalive'),
            'keepAliveIntervalMs' => 60000,
            'texts' => [
               'email' => __('Email'),
               'password' => __('Password'),
               'rememberMe' => __('Remember me'),
               'signIn' => __('Sign in'),
               'forgotPassword' => __('Forgot password?'),
               'or' => __('or'),
               'genericError' => __('Something went wrong. Please try again.'),
               'keepAliveWarning' => __('Connection to session keep-alive failed. Your session may expire.'),
            ],
            'passwordResetTexts' => [
               'email' => __('Email'),
               'password' => __('Password'),
               'repeatPassword' => __('Repeat password'),
               'resetPassword' => __('Reset password'),
               'genericError' => __('Something went wrong. Please try again.'),
               'keepAliveWarning' => __('Connection to session keep-alive failed. Your session may expire.'),
            ],
         ]]);
      }

      abort(500, 'No authentication method is configured');
   }
}
