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
use Laravel\Socialite\Contracts\User as OAuthUserContract;
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
            $user = $this->findUser($provider, $oauthUser);
        } catch (AccessDeniedHttpException $exception) {
            return to_route('login')->with('oauth_error', $exception->getMessage());
        }

        Auth::login($user, true);

        return to_route('home')->with('oauth_success', __('auth.oauth.login_success'));
    }

    private function findUser(string $provider, OAuthUserContract $oauthUser): User
    {
        $email = $oauthUser->getEmail();

        if (! $email) {
            throw new AccessDeniedHttpException(__('auth.oauth.missing_email'));
        }

        $normalizedEmail = Str::lower($email);
        $user = User::query()->where('email', $normalizedEmail)->first();

        if ($user !== null) {
            return $user;
        }

        $newUser = new User();
        $newUser->forceFill([
            'name' => (string) ($oauthUser->getName() ?: $normalizedEmail),
            'email' => $normalizedEmail,
            'password' => Hash::make(Str::random(32)),
            'enabled' => true,
            'externalproviderid' => $provider,
            'external_id' => (string) $oauthUser->getId(),
            'email_verified_at' => Carbon::now(),
        ]);
        $newUser->save();

        return $newUser;
    }

}
