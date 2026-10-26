<?php

namespace Ledningssystemet\Ledningssystemet\Http\Controllers\Auth;

use Ledningssystemet\Ledningssystemet\Http\Controllers\Controller;
use Ledningssystemet\Ledningssystemet\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\Config as SocialiteConfig;
use Throwable;

class SsoController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(self::isProviderEnabled($provider), 404);

        session(['auth_mechanism' => 'SSO_'.strtoupper($provider)]);

        return self::socialiteDriver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(self::isProviderEnabled($provider), 404);

        try {
            $socialUser = self::socialiteDriver($provider)->user();
        } catch (Throwable $e) {
            Log::warning('SSO callback failed for '.$provider.': '.$e->getMessage());

            return redirect()->route('login')->withErrors(trans('auth.failed'));
        }

        $user = $this->resolveUser($provider, $socialUser->getEmail(), $socialUser->getName(), $socialUser->getId());
        if (!$user->enabled) {
            Log::info('User '.$user->email.' is disabled and cannot sign in with '.$provider.'.');
            abort(403);
        }

        $mfaEnforced = config('ledningssystemet.mfa_enabled') && config('ledningssystemet.mfa_enforced');
        $hasConfiguredMfa = !empty($user->two_factor_secret)
            && !empty($user->two_factor_recovery_codes)
            && !empty($user->two_factor_confirmed_at);

        if ($mfaEnforced && !$hasConfiguredMfa) {
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('auth_mechanism', 'SSO_'.strtoupper($provider));
            $request->session()->regenerateToken();

            return redirect()->route('mfa.enforcement');
        }

        $request->session()->regenerate();
        if ($mfaEnforced && !$this->hasTrustedIdpMfa($provider, $socialUser)) {
            $request->session()->put('login.id', $user->getAuthIdentifier());
            $request->session()->put('login.auth_mechanism', 'SSO_'.strtoupper($provider));
            $request->session()->regenerateToken();

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->put('auth_mechanism', 'SSO_'.strtoupper($provider));

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended('/');
    }

    public static function enabledProviders(): array
    {
        $providers = [];

        foreach (['azure', 'google'] as $provider) {
            if (self::isProviderEnabled($provider)) {
                $providers[] = [
                    'name' => $provider,
                    'label' => self::providerLabel($provider),
                ];
            }
        }

        return $providers;
    }

    public static function hasEnabledProviders(): bool
    {
        return [] !== self::enabledProviders();
    }

    public static function isProviderEnabled(string $provider): bool
    {
        return null !== self::providerConfig($provider);
    }

    public static function logoutRedirectUrl(string $fallbackUrl): string
    {
        $mechanism = (string) session('auth_mechanism', '');
        if ('SSO_AZURE' !== $mechanism) {
            return $fallbackUrl;
        }

        $configured = config('services.azure.logout');
        if (is_string($configured) && '' !== trim($configured)) {
            return $configured;
        }

        $tenant = config('services.azure.tenant', 'common');

        return 'https://login.microsoftonline.com/'.trim((string) $tenant).'/oauth2/v2.0/logout?post_logout_redirect_uri='.urlencode($fallbackUrl);
    }

    protected function resolveUser(string $provider, string $email, string $name, string $providerUserId): User
    {
        $email = self::normalizeAzureGuestEmail($provider, (string) ($email ?? ''));
        $name = trim((string) ($name ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || '' === $name) {
            Log::warning('SSO callback produced invalid identity data for provider '.$provider.'.');
            abort(403);
        }

        $providerKey = self::providerExternalId($provider, $providerUserId);

        $user = User::query()
            ->where('email', $email)
            ->orWhere('external_id', $providerKey)
            ->first();

        if (null === $user) {
            Log::warning('User '.$email.' was not found for SSO provider '.$provider.'.');
            abort(403);
        }

        if (null === $user->external_id && null !== $providerKey) {
            $user->external_id = $providerKey;
        }

        if ($user->email !== $email) {
            $user->email = $email;
        }

        if ($user->name !== $name) {
            $user->name = $name;
        }

        if ($user->isDirty(['external_id', 'email', 'name'])) {
            $user->save();
        }

        return $user;
    }

    protected static function normalizeAzureGuestEmail(string $provider, string $email): string
    {
        $email = strtolower(trim($email));
        if ('azure' !== $provider || !preg_match('/^([^#]+)#ext#@[^@]+$/i', $email, $matches)) {
            return $email;
        }

        $separator = strrpos($matches[1], '_');
        if (false === $separator) {
            return $email;
        }

        return substr($matches[1], 0, $separator).'@'.substr($matches[1], $separator + 1);
    }

    protected function hasTrustedIdpMfa(string $provider, object $socialUser): bool
    {
        if (!filter_var(config('services.'.$provider.'.trust_idp_mfa', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $claims = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : null;
        if (!is_array($claims)) {
            return false;
        }

        $acr = $claims['acr'] ?? null;
        $trustedAcrValues = config('services.'.$provider.'.mfa_acr_values', []);
        if (is_string($trustedAcrValues)) {
            $trustedAcrValues = explode(',', $trustedAcrValues);
        }

        if (is_string($acr) && '' !== $acr && is_array($trustedAcrValues)) {
            foreach ($trustedAcrValues as $trustedAcr) {
                if (is_string($trustedAcr) && hash_equals(trim($trustedAcr), $acr)) {
                    return true;
                }
            }
        }

        $amr = $claims['amr'] ?? [];
        if (is_string($amr)) {
            $amr = [$amr];
        }

        if (!is_array($amr)) {
            return false;
        }

        $factors = [];
        foreach ($amr as $method) {
            if (!is_string($method)) {
                continue;
            }

            $method = strtolower(trim($method));
            if ('mfa' === $method) {
                return true;
            }

            $factor = match ($method) {
                'pwd', 'password', 'pin' => 'knowledge',
                'otp', 'sms', 'hwk', 'swk', 'fido', 'u2f' => 'possession',
                'fpt', 'face', 'iris', 'retina', 'voice' => 'inherence',
                default => null,
            };

            if (null !== $factor) {
                $factors[$factor] = true;
            }
        }

        return count($factors) > 1;
    }

    protected static function socialiteDriver(string $provider)
    {
        $config = self::providerConfig($provider);
        abort_unless(null !== $config, 404);

        $driver = Socialite::driver($provider)->setConfig($config);
        $scopes = self::providerScopes($provider);

        return [] === $scopes ? $driver : $driver->scopes($scopes);
    }

    protected static function providerConfig(string $provider): ?SocialiteConfig
    {
        return match ($provider) {
            'azure' => self::buildAzureConfig(),
            'google' => self::buildGoogleConfig(),
            default => null,
        };
    }

    protected static function buildAzureConfig(): ?SocialiteConfig
    {
        $clientId = config('services.azure.client_id');
        $clientSecret = config('services.azure.client_secret');
        $redirect = config('services.azure.redirect', route('sso.callback', ['provider' => 'azure']));

        if (!self::hasCredentials($clientId, $clientSecret, $redirect)) {
            return null;
        }

        return new SocialiteConfig($clientId, $clientSecret, $redirect, [
            'tenant' => config('services.azure.tenant', 'common'),
            'proxy' => config('services.azure.proxy'),
            'graph_url' => config('services.azure.graph_url', 'https://graph.microsoft.com/v1.0/me'),
        ]);
    }

    protected static function buildGoogleConfig(): ?SocialiteConfig
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirect = config('services.google.redirect', route('sso.callback', ['provider' => 'google']));

        if (!self::hasCredentials($clientId, $clientSecret, $redirect)) {
            return null;
        }

        return new SocialiteConfig($clientId, $clientSecret, $redirect, [
            'hosted_domain' => config('services.google.hosted_domain'),
        ]);
    }

    protected static function providerScopes(string $provider): array
    {
        $configured = match ($provider) {
            'azure' => config('services.azure.scope', 'openid,email,profile,offline_access,User.Read'),
            'google' => config('services.google.scope', 'openid,email,profile'),
            default => '',
        };

        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) $configured))));

        if ('azure' === $provider && !in_array('User.Read', $scopes, true)) {
            $scopes[] = 'User.Read';
        }

        return $scopes;
    }

    protected static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'azure' => (string) config('ledningssystemet.sso_azure_button_text', 'Microsoft Entra ID'),
            'google' => (string) config('ledningssystemet.sso_google_button_text', 'Google Workspace'),
            default => Str::title($provider),
        };
    }

    protected static function hasCredentials(mixed $clientId, mixed $clientSecret, mixed $redirect): bool
    {
        return is_string($clientId) && '' !== trim($clientId)
            && is_string($clientSecret) && '' !== trim($clientSecret)
            && is_string($redirect) && '' !== trim($redirect);
    }

    protected static function providerExternalId(string $provider, ?string $providerUserId): ?string
    {
        $providerUserId = is_string($providerUserId) ? trim($providerUserId) : '';

        return '' === $providerUserId ? null : $provider.':'.$providerUserId;
    }

}
