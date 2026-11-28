<?php

namespace Ledningssystemet\Ledningssystemet\Providers;

use Ledningssystemet\Ledningssystemet\Actions\Fortify\GenericPasswordResetLinkResponse;
use Ledningssystemet\Ledningssystemet\Actions\Fortify\ResetUserPassword;
use Ledningssystemet\Ledningssystemet\Actions\Fortify\RedirectIfTwoFactorAuthenticatable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RedirectsIfTwoFactorAuthenticatable::class, RedirectIfTwoFactorAuthenticatable::class);

        // Always respond identically to password reset link requests,
        // whether or not the submitted email address belongs to a real
        // user, to prevent user enumeration via the "forgot password" form.
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::requestPasswordResetLinkView(fn () => view('auth.react-shell', ['props' => [
            'screen' => 'password-reset',
            'pageClass' => 'passwordreset',
            'formAction' => route('password.email'),
            'resettingPassword' => false,
            'logoSrc' => file_exists(resource_path().'/brand/logo/logo_passwordreset.png')
                ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_passwordreset.png'))
                : '/images/logo_'.app()->getLocale().'_white.png',
            'keepAliveUrl' => route('auth.keepalive'),
            'keepAliveIntervalMs' => 60000,
            'texts' => [
                'email' => __('Email'),
                'password' => __('Password'),
                'repeatPassword' => __('Repeat password'),
                'resetPassword' => __('Reset password'),
                'genericError' => __('Something went wrong. Please try again.'),
                'keepAliveWarning' => __('Connection to session keep-alive failed. Your session may expire.'),
            ],
        ]]));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.react-shell', ['props' => [
            'screen' => 'password-reset',
            'pageClass' => 'passwordreset',
            'formAction' => route('password.update'),
            'resettingPassword' => true,
            'email' => $request->email,
            'token' => $request->route('token'),
            'logoSrc' => file_exists(resource_path().'/brand/logo/logo_passwordreset.png')
                ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_passwordreset.png'))
                : '/images/logo_'.app()->getLocale().'_white.png',
            'keepAliveUrl' => route('auth.keepalive'),
            'keepAliveIntervalMs' => 60000,
            'texts' => [
                'email' => __('Email'),
                'password' => __('Password'),
                'repeatPassword' => __('Repeat password'),
                'resetPassword' => __('Reset password'),
                'genericError' => __('Something went wrong. Please try again.'),
                'keepAliveWarning' => __('Connection to session keep-alive failed. Your session may expire.'),
            ],
        ]]));

        // Fortify may invoke the callback twice during one login request.
        // Weak keys release the result with the request, including in long-lived workers.
        $verifiedRequests = new \WeakMap();
        $credentialsKey = random_bytes(32);

        Fortify::authenticateUsing(function (Request $request) use ($verifiedRequests, $credentialsKey) {
            if (!config('ledningssystemet.login_allow_passwordlogin')) {
                unset($verifiedRequests[$request]);

                return null;
            }

            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $provider = Auth::getProvider();
            $fingerprint = hash_hmac('sha256', serialize($credentials), $credentialsKey);
            $verified = $verifiedRequests[$request] ?? null;

            if ($verified !== null
                && $verified['provider'] === $provider
                && hash_equals($verified['fingerprint'], $fingerprint)
                && $verified['user']->enabled
                && $verified['passwordHash'] === $verified['user']->getAuthPassword()) {
                return $verified['user'];
            }

            unset($verifiedRequests[$request]);
            $user = $provider->retrieveByCredentials($credentials);

            // Always run a Hash::check(), even when no user was found, using
            // a dummy hash so that the response time does not depend on
            // whether the submitted email address exists. This prevents a
            // timing side-channel attack that would otherwise allow an
            // attacker to enumerate valid accounts.
            $hashedPassword = $user?->getAuthPassword() ?? '$2y$10$8U9OMFcAMU1kNjIfAqPMdujKY5r/4YEfw5xY.eFcCcxSC9zvJvMxK';
            $passwordMatches = Hash::check($credentials['password'], $hashedPassword);

            if (null === $user || !$passwordMatches) {
                Log::warning('User login failed for '.$credentials['email'].' from IP '.$request->ip());

                return null;
            }

            if (!$user->enabled) {
                Log::warning('User login failed for '.$credentials['email'].' (User is not enabled) from IP '.$request->ip());

                return null;
            }

            Log::info('User password verified for '.$credentials['email'].' from IP '.$request->ip());

            $verifiedRequests[$request] = [
                'provider' => $provider,
                'fingerprint' => $fingerprint,
                'user' => $user,
                'passwordHash' => $hashedPassword,
            ];

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().$request->input('email'));
        });

        if (config('ledningssystemet.mfa_enabled')) {
            RateLimiter::for('two-factor', function (Request $request) {
                return Limit::perMinute(5)->by($request->session()->get('login.id', $request->ip()));
            });
        }
    }
}
