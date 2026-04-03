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

        return view('auth.login', [
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

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], (bool) ($credentials['remember'] ?? false))) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Felaktiga inloggningsuppgifter.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user instanceof User) {
            Auth::logout();

            return to_route('login')->withErrors([
                'email' => 'Kunde inte slutfora inloggningen.',
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

        Mail::raw("Din engangskod ar: {$otpCode}. Koden ar giltig i ".AuthFlow::mfaOtpTtlMinutes().' minuter.', function ($message) use ($user): void {
            $message->to($user->email)->subject('Engangskod for inloggning');
        });
    }

    private function otpCacheKey(int $userId): string
    {
        return 'auth:otp:'.$userId;
    }
}

