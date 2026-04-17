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
            $user = $this->findOrCreateUser($provider, $oauthUser->getId(), $oauthUser->getEmail(), $oauthUser->getName());
        } catch (AccessDeniedHttpException $exception) {
            return to_route('login')->with('oauth_error', $exception->getMessage());
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return to_route('home')->with('oauth_success', __('auth.oauth.login_success'));
    }

    private function findOrCreateUser(string $provider, ?string $externalId, ?string $email, ?string $name): User
    {
        if (! $externalId) {
            throw new AccessDeniedHttpException(__('auth.oauth.invalid_user_id'));
        }

        if (! $email) {
            throw new AccessDeniedHttpException(__('auth.oauth.missing_email'));
        }

        $email = Str::lower($email);
        $displayName = $name ?: Str::before($email, '@');

        $user = User::query()
            ->where('externalproviderid', $provider)
            ->where('external_id', $externalId)
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user) {
            $user = new User();
            $user->forceFill([
                'name' => $displayName,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'enabled' => true,
                'externalproviderid' => $provider,
                'external_id' => $externalId,
                'email_verified_at' => Carbon::now(),
                'last_login_at' => Carbon::now(),
            ]);
            $user->save();

            return $user;
        }

        if ($user->externalproviderid && $user->externalproviderid !== $provider) {
            throw new AccessDeniedHttpException(__('auth.oauth.linked_to_other_provider'));
        }

        if ($user->external_id && $user->external_id !== $externalId) {
            throw new AccessDeniedHttpException(__('auth.oauth.linked_to_other_identity'));
        }

        $user->forceFill([
            'name' => $displayName,
            'externalproviderid' => $provider,
            'external_id' => $externalId,
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
            'last_login_at' => Carbon::now(),
        ]);
        $user->save();

        return $user;
    }

}

