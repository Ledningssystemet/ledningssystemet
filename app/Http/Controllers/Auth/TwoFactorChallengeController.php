<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\RedirectIfTwoFactorAuthenticatable;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        if (!config('ledningssystemet.mfa_enabled')) {
            return redirect()->route('login');
        }

        if (!$request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return view('auth.react-shell', ['props' => [
            'screen' => 'two-factor',
            'pageClass' => 'login',
            'mfaEnabled' => true,
            'formAction' => route('two-factor.login'),
            'logoSrc' => file_exists(resource_path().'/brand/logo/logo_login.png')
                ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_login.png'))
                : '/images/logo_'.app()->getLocale().'_white.png',
            'keepAliveUrl' => route('auth.keepalive'),
            'keepAliveIntervalMs' => 60000,
            'texts' => [
                'codeInstruction' => __('Enter the code from your authenticator app to finish signing in.'),
                'authenticationCode' => __('Authentication code'),
                'orUseRecoveryCode' => __('or use a recovery code'),
                'recoveryCode' => __('Recovery code'),
                'verify' => __('Verify'),
                'genericError' => __('Something went wrong. Please try again.'),
                'keepAliveWarning' => __('Connection to session keep-alive failed. Your session may expire.'),
                'trustDevice' => __('Trust this device for :days days', ['days' => config('ledningssystemet.trusted_device_days', 30)]),
            ],
        ]]);
    }

    public function store(TwoFactorLoginRequest $request): TwoFactorLoginResponse|RedirectResponse
    {
        if (!config('ledningssystemet.mfa_enabled')) {
            return redirect()->route('login');
        }

        $loginId = $request->session()->get('login.id');
        if (null === $loginId) {
            return redirect()->route('login');
        }

        $user = Auth::getProvider()->retrieveById($loginId);
        if (null === $user) {
            $request->session()->forget('login');

            return redirect()->route('login')->withErrors(trans('auth.failed'));
        }

        if (!$user->enabled) {
            $request->session()->forget('login');

            return redirect()->route('login')->withErrors(trans('auth.failed'));
        }

        $request->setUserResolver(fn () => $user);

        $validRecoveryCode = $request->validRecoveryCode();
        if ($validRecoveryCode) {
            $user->replaceRecoveryCode($validRecoveryCode);
        } elseif (!$request->hasValidCode()) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ]);
        }

        Auth::guard(config('fortify.guard'))->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('login');
        $request->session()->put('auth_mechanism', 'PASSWORD');

        if ($request->boolean('trust_device')) {
            $this->rememberTrustedDevice($request, $user);
        }

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * Persist a trusted-device record for the user and queue a cookie that
     * identifies this browser so future logins can skip the two-factor
     * challenge until the trust expires. Several devices can be trusted per
     * user at the same time.
     */
    protected function rememberTrustedDevice(Request $request, $user): void
    {
        $days = (int) config('ledningssystemet.trusted_device_days', 30);
        $token = Str::random(64);

        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'device_name' => $request->userAgent(),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        Cookie::queue(Cookie::make(
            RedirectIfTwoFactorAuthenticatable::COOKIE_NAME,
            $token,
            $days * 24 * 60,
            null,
            null,
            null,
            true,
            false,
            'lax'
        ));
    }

    public function enable(Request $request): RedirectResponse
    {
        if (!config('ledningssystemet.mfa_enabled')) {
            return redirect()->route('login');
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        abort_if(null === $user, 401);

        if (!Hash::check($request->string('password')->value(), $user->getAuthPassword())) {
            return back()->withErrors(['password' => __('The provided password does not match our records.')]);
        }

        return redirect()->route('two-factor.enable');
    }
}
