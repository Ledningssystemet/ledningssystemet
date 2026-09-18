<?php

namespace App\Actions\Fortify;

use App\Models\TrustedDevice;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as BaseRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Extends Fortify's default two-factor redirect action so that logins from a
 * device the user previously chose to trust (see TwoFactorChallengeController)
 * skip the two-factor challenge entirely until the trust expires.
 */
class RedirectIfTwoFactorAuthenticatable extends BaseRedirectIfTwoFactorAuthenticatable
{
    public const COOKIE_NAME = 'trusted_device';

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
       $user = $this->validateCredentials($request);
        if (!config('ledningssystemet.mfa_enabled')) {
            return $next($request);
        }

       if(config('ledningssystemet.mfa_enforced') || $this->userHasTwoFactorEnabled($user)) {
          if ($this->hasTrustedDeviceCookie($request, $user)) {
             return $next($request);
          }

          return $this->twoFactorChallengeResponse($request, $user);
       }

       return $next($request);
    }

    protected function userHasTwoFactorEnabled($user): bool
    {
        if (null === $user || !in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))) {
            return false;
        }

        if (Fortify::confirmsTwoFactorAuthentication()) {
            return !empty($user->two_factor_secret) && !is_null($user->two_factor_confirmed_at);
        }

        return !empty($user->two_factor_secret);
    }

    protected function hasTrustedDeviceCookie($request, $user): bool
    {
        $token = $request->cookie(static::COOKIE_NAME);
        if (empty($token) || null === $user) {
            return false;
        }

        $device = TrustedDevice::query()
            ->where('user_id', $user->getKey())
            ->where('token_hash', hash('sha256', (string) $token))
            ->notExpired()
            ->first();

        if (null === $device) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }
}
