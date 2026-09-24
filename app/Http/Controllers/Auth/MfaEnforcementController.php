<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

class MfaEnforcementController extends Controller
{
    public function index(Request $request)
    {
        if (!config('ledningssystemet.mfa_enabled') || !config('ledningssystemet.mfa_enforced')) {
            return redirect()->route('home');
        }

        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!empty($request->user()->two_factor_secret) && !empty($request->user()->two_factor_recovery_codes) && !empty($request->user()->two_factor_confirmed_at)) {
            return redirect()->route('home');
        }

        return response()->view('auth.react-shell', ['props' => [
            'screen' => 'mfa-enforcement',
            'pageClass' => 'login',
            'mfaEnabled' => true,
            'formAction' => route('two-factor.enable'),
            'confirmAction' => route('two-factor.confirm'),
            'qrCodeUrl' => route('two-factor.qr-code'),
            'recoveryCodesUrl' => route('two-factor.recovery-codes'),
            'status' => session('status'),
            'logoSrc' => file_exists(resource_path().'/brand/logo/logo_login.png')
                ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_login.png'))
                : '/images/logo_'.app()->getLocale().'_white.png',
            'texts' => [
                'message' => __('Your organization requires MFA. Configure it here before you can continue.'),
                'action' => __('Start MFA setup'),
                'verificationCode' => __('Verification code'),
                'confirm' => __('Confirm and continue'),
                'scanQr' => __('1. Scan the QR code in your authenticator app.'),
                'enterCode' => __('2. Enter the 6-digit code to finish MFA setup.'),
                'recoveryCodes' => __('Recovery codes'),
                'saveRecoveryCodes' => __('Save these recovery codes in a safe place before continuing.'),
                'setupComplete' => __('MFA is ready. Redirecting you into the system...'),
                'genericError' => __('Something went wrong. Please try again.'),
                'keepAliveWarning' => __('Connection to session keep-alive failed. Your session may expire.'),
            ],
            'fortifyStatuses' => [
                'enabled' => Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED,
                'confirmed' => Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED,
                'recoveryCodesGenerated' => Fortify::RECOVERY_CODES_GENERATED,
            ],
        ]]);
    }
}
