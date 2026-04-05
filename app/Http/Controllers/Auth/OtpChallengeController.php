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

class OtpChallengeController extends Controller
{
    public function create(Request $request)
    {
        if (! $request->session()->has('otp.pending_user_id')) {
            return to_route('login');
        }

        return view('auth.otp-challenge', [
            'ttlMinutes' => AuthFlow::mfaOtpTtlMinutes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $userId = (int) $request->session()->get('otp.pending_user_id');
        $remember = (bool) $request->session()->get('otp.remember', false);

        if ($userId <= 0) {
            return to_route('login');
        }

        $payload = Cache::get($this->otpCacheKey($userId));

        if (! is_array($payload) || ! isset($payload['hash'], $payload['expires_at'])) {
            return back()->withErrors(['otp' => __('auth.otp.expired')]);
        }

        $expiresAt = Carbon::parse((string) $payload['expires_at']);

        if ($expiresAt->isPast() || ! Hash::check((string) $request->string('otp'), (string) $payload['hash'])) {
            return back()->withErrors(['otp' => __('auth.otp.invalid')]);
        }

        $user = User::query()->find($userId);

        if (! $user) {
            $this->clearOtpState($request, $userId);

            return to_route('login');
        }

        Auth::login($user, $remember);
        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        $this->clearOtpState($request, $userId);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $userId = (int) $request->session()->get('otp.pending_user_id');

        if ($userId <= 0) {
            return to_route('login');
        }

        $user = User::query()->find($userId);

        if (! $user) {
            $this->clearOtpState($request, $userId);

            return to_route('login');
        }

        $otpCode = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(AuthFlow::mfaOtpTtlMinutes());

        Cache::put($this->otpCacheKey($user->id), [
            'hash' => Hash::make($otpCode),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        Mail::raw(__('auth.otp.email_resend_body', [
            'code' => $otpCode,
            'minutes' => AuthFlow::mfaOtpTtlMinutes(),
        ]), function ($message) use ($user): void {
            $message->to($user->email)->subject(__('auth.otp.email_resend_subject'));
        });

        return back()->with('status', __('auth.otp.resent'));
    }

    private function clearOtpState(Request $request, int $userId): void
    {
        Cache::forget($this->otpCacheKey($userId));
        $request->session()->forget(['otp.pending_user_id', 'otp.remember']);
    }

    private function otpCacheKey(int $userId): string
    {
        return 'auth:otp:'.$userId;
    }
}

