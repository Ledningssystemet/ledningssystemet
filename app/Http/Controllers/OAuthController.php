<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuthFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! AuthFlow::oauthConfigured()) {
            abort(404);
        }

        $provider = AuthFlow::oauthProvider();
        $driver = AuthFlow::oauthDriver();

        if (! $provider || ! $driver) {
            abort(404);
        }

        $socialite = Socialite::driver($driver);

        if ($provider === 'google' && config('services.google.hosted_domain')) {
            $socialite = $socialite->with([
                'hd' => config('services.google.hosted_domain'),
            ]);
        }

        return $socialite->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! AuthFlow::oauthConfigured()) {
            abort(404);
        }

        if ($request->filled('error')) {
            return to_route('login')->with('oauth_error', __('auth.oauth.cancelled_or_denied'));
        }

        $provider = AuthFlow::oauthProvider();
        $driver = AuthFlow::oauthDriver();


        if (! $provider || ! $driver) {
            abort(404);
        }

        $oauthUser = Socialite::driver($driver)->user();

        try {
            $user = $this->findUser($provider, $oauthUser->getEmail());
        } catch (AccessDeniedHttpException $exception) {
            return to_route('login')->with('oauth_error', $exception->getMessage());
        }

        if(null == $user)
        {
            $request->session()->regenerate();
            abort(403, __('auth.oauth.permission_denied'));
        }

        Auth::login($user, true);

        return to_route('home')->with('oauth_success', __('auth.oauth.login_success'));
    }

    private function findUser(string $provider, ?string $email): User
    {
        if (! $email) {
            throw new AccessDeniedHttpException(__('auth.oauth.missing_email'));
        }

        return User::query()->where('email', Str::lower($email))->first();
    }

}

