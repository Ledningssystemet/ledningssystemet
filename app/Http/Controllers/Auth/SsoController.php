<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_mechanism', 'SSO_'.strtoupper($provider));

        if (config('ledningssystemet.mfa_enabled') && config('ledningssystemet.mfa_enforced') && empty($user->two_factor_secret)) {
            $request->session()->regenerateToken();
            return redirect()->route('mfa.enforcement');
        }

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
        $email = strtolower(trim((string) ($email ?? '')));
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

        if (!is_string($clientId) || '' === trim($clientId)) {
            $clientId = self::inferAzureClientIdFromLegacyAuthorizeUrl();
        }

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

    protected static function inferAzureClientIdFromLegacyAuthorizeUrl(): ?string
    {
        $authorizeUrl = (string) config('ledningssystemet.oauth_server_authorize_url', '');
        if ('' === trim($authorizeUrl)) {
            return null;
        }

        $path = parse_url($authorizeUrl, PHP_URL_PATH);
        if (!is_string($path) || '' === trim($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($segments) < 3) {
            return null;
        }

        if ('oauth2' !== ($segments[1] ?? null) || 'v2.0' !== ($segments[2] ?? null)) {
            return null;
        }

        $candidate = trim((string) ($segments[0] ?? ''));

        return '' === $candidate ? null : $candidate;
    }
}
