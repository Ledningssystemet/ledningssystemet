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
            return to_route('login')->with('oauth_error', 'OAuth login was cancelled or denied.');
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

        return to_route('home')->with('oauth_success', 'You are now logged in.');
    }

    private function findOrCreateUser(string $provider, ?string $externalId, ?string $email, ?string $name): User
    {
        if (! $externalId) {
            throw new AccessDeniedHttpException('The OAuth provider did not return a valid user id.');
        }

        if (! $email) {
            throw new AccessDeniedHttpException('The OAuth provider did not return an email address.');
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
            $user = User::query()->create([
                'name' => $displayName,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'enabled' => true,
                'externalproviderid' => $provider,
                'external_id' => $externalId,
                'email_verified_at' => Carbon::now(),
                'last_login_at' => Carbon::now(),
            ]);

            return $user;
        }

        if ($user->externalproviderid && $user->externalproviderid !== $provider) {
            throw new AccessDeniedHttpException('This account is linked to another OAuth provider.');
        }

        if ($user->external_id && $user->external_id !== $externalId) {
            throw new AccessDeniedHttpException('This account is already linked to another external identity.');
        }

        $user->fill([
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


