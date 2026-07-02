<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use RuntimeException;

class LoginController extends Controller
{
    public function create(Request $request)
    {
        if (Auth::check()) {
            return to_route('home');
        }

        if (AuthFlow::oauthOnly() && AuthFlow::oauthConfigured()) {
            return to_route('oauth.redirect');
        }

        return Inertia::render('auth/Login', [
            'showPasswordForm' => AuthFlow::passwordLoginEnabled(),
            'showOauthButton' => AuthFlow::oauthConfigured(),
            'mfaEnabled' => AuthFlow::mfaEnabled(),
            'mfaEnforced' => AuthFlow::mfaEnforced(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! AuthFlow::passwordLoginEnabled()) {
            return to_route('oauth.redirect');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'use_otp' => ['sometimes', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);

        if (! $this->attemptLogin($credentials['email'], $credentials['password'], $remember)) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('auth.errors.invalid_credentials'),
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user instanceof User) {
            Auth::logout();

            return to_route('login')->withErrors([
                'email' => __('auth.errors.login_failed'),
            ]);
        }

        $mustUseOtp = AuthFlow::mfaEnforced() || (AuthFlow::mfaEnabled() && $request->boolean('use_otp'));

        if ($mustUseOtp) {
            $this->startOtpChallenge($request, $user, (bool) ($credentials['remember'] ?? false));

            return to_route('otp.challenge');
        }

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    private function startOtpChallenge(Request $request, User $user, bool $remember): void
    {
        Auth::logout();

        $request->session()->put('otp.pending_user_id', $user->id);
        $request->session()->put('otp.remember', $remember);

        $otpCode = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(AuthFlow::mfaOtpTtlMinutes());

        Cache::put($this->otpCacheKey($user->id), [
            'hash' => Hash::make($otpCode),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        Mail::raw(__('auth.otp.email_body', [
            'code' => $otpCode,
            'minutes' => AuthFlow::mfaOtpTtlMinutes(),
        ]), function ($message) use ($user): void {
            $message->to($user->email)->subject(__('auth.otp.email_subject'));
        });
    }

    private function otpCacheKey(int $userId): string
    {
        return 'auth:otp:'.$userId;
    }

    private function attemptLogin(string $email, string $password, bool $remember): bool
    {
        try {
            return Auth::attempt(['email' => $email, 'password' => $password], $remember);
        } catch (RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'does not use the Bcrypt algorithm')) {
                throw $exception;
            }

            return $this->attemptLegacyBcryptLogin($email, $password, $remember);
        }
    }

    private function attemptLegacyBcryptLogin(string $email, string $password, bool $remember): bool
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || ! is_string($user->password) || $user->password === '') {
            return false;
        }

        if (! str_starts_with($user->password, '$2a$') && ! str_starts_with($user->password, '$2b$')) {
            return false;
        }

        if (! password_verify($password, $user->password)) {
            return false;
        }

        Auth::login($user, $remember);

        // Upgrade legacy bcrypt variants to the framework default hash format.
        $user->forceFill(['password' => Hash::make($password)])->save();

        return true;
    }
}

