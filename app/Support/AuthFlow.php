<?php

namespace App\Support;

class AuthFlow
{
    public static function loginMode(): string
    {
        return (string) config('authentication.login_mode', 'hybrid');
    }

    public static function passwordLoginEnabled(): bool
    {
        return self::loginMode() !== 'oauth';
    }

    public static function oauthOnly(): bool
    {
        return self::loginMode() === 'oauth';
    }

    public static function oauthProvider(): ?string
    {
        $provider = strtolower((string) config('authentication.oauth.provider', ''));

        return in_array($provider, ['google', 'microsoft'], true) ? $provider : null;
    }

    public static function oauthDriver(): ?string
    {
        return match (self::oauthProvider()) {
            'google' => 'google',
            'microsoft' => 'graph',
            default => null,
        };
    }

    public static function oauthConfigured(): bool
    {
        return (bool) config('authentication.oauth.enabled', false)
            && self::oauthProvider() !== null
            && filled(config('authentication.oauth.client_id'))
            && filled(config('authentication.oauth.client_secret'))
            && filled(config('authentication.oauth.redirect'));
    }

    public static function mfaEnabled(): bool
    {
        return (bool) config('authentication.mfa.enabled', false);
    }

    public static function mfaEnforced(): bool
    {
        return self::mfaEnabled() && (bool) config('authentication.mfa.enforce', false);
    }

    public static function mfaOtpTtlMinutes(): int
    {
        return (int) config('authentication.mfa.otp_ttl', 10);
    }
}

