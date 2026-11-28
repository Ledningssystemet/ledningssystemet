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

      session(['auth_mechanism' => 'SSO_' . strtoupper($provider)]);

      return self::socialiteDriver($provider)->redirect();
   }

   public function callback(Request $request, string $provider): RedirectResponse
   {
      abort_unless(self::isProviderEnabled($provider), 404);

      try {
         $socialUser = self::socialiteDriver($provider)->user();
      } catch (Throwable $e) {
         Log::warning('SSO callback failed for ' . $provider . ': ' . $e->getMessage() . ' from IP ' . $request->ip() . '.');

         return redirect()->route('login')->withErrors(trans('auth.failed'));
      }

      $user = $this->resolveUser($provider, $socialUser->getEmail(), $socialUser->getName(), $socialUser->getId(), $socialUser);
      if (!$user->enabled) {
         Log::info('User ' . $user->email . ' is disabled and cannot sign in with ' . $provider . ' from IP ' . $request->ip() . '.');
         abort(403);
      }

      Auth::login($user);
      $request->session()->regenerate();
      $request->session()->put('auth_mechanism', 'SSO_' . strtoupper($provider));

      if (config('ledningssystemet.mfa_enabled') && config('ledningssystemet.mfa_enforced') && empty($user->two_factor_secret)) {
         Log::info('User ' . $user->email . ' is required to enforce MFA after SSO login with ' . $provider . ' from IP ' . $request->ip() . '.');
         $request->session()->regenerateToken();
         return redirect()->route('mfa.enforcement');
      }

      Log::info('User ' . $user->email . ' signed in with ' . $provider . ' from IP ' . $request->ip() . '.');

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
      $mechanism = (string)session('auth_mechanism', '');
      if ('SSO_AZURE' !== $mechanism) {
         return $fallbackUrl;
      }

      $configured = config('services.azure.logout');
      if (is_string($configured) && '' !== trim($configured)) {
         return $configured;
      }

      $tenant = config('services.azure.tenant', 'common');

      return 'https://login.microsoftonline.com/' . trim((string)$tenant) . '/oauth2/v2.0/logout?post_logout_redirect_uri=' . urlencode($fallbackUrl);
   }

   protected function resolveUser(string $provider, string $email, string $name, string $providerUserId, $socialUser): User
   {
      $actualEmail = $email;

      // Attempt to find the user by the provider's unique identifier first
      $user = (null !== $providerUserId)
         ? User::query()->where('external_id', $providerUserId)->first()
         : null;

      // Attempt to lookup user by primary email
      if(null === $user && null !== $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $user = User::query()->where('email', strtolower(trim($email)))->first();
         if(null !== $user)
            Log::info('Found user by primary email ' . $email . ' for SSO provider ' . $provider . '.');
      }

      // Attempt to lookup user by social user mail attribute
      if(null === $user && null !== $email && $socialUser->user['mail'] &&  filter_var($socialUser->user['mail'], FILTER_VALIDATE_EMAIL)) {
         $user = User::query()->where('email', strtolower(trim($socialUser->user['mail'])))->first();
         if(null !== $user){
            $actualEmail = $socialUser->user['mail'];
            Log::info('Found user by social user mail attribute ' . $actualEmail. ' for SSO provider ' . $provider . '.');
         }
      }

      // Attempt to lookup user by social user otherMails attribute
      if(null === $user && null !== $email && is_array($socialUser->user['otherMails'])) {
         foreach($socialUser->user['otherMails'] as $otherMail) {
            if(filter_var($otherMail, FILTER_VALIDATE_EMAIL)) {
               $user = User::query()->where('email', strtolower(trim($otherMail)))->first();
               if(null !== $user) {
                  $actualEmail = $otherMail;
                  Log::info('Found user by social user otherMails attribute ' . $actualEmail. ' for SSO provider ' . $provider . '.');
                  break;
               }
            }
         }
      }

      $email = strtolower(trim((string)($actualEmail ?? '')));
      $name = trim((string)($name ?? ''));

      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || '' === $name) {
         Log::warning('SSO callback produced invalid identity data ("'.$email.'") for provider ' . $provider . '.');
         abort(403);
      }


      if (null === $user) {
         $user = User::query()->where('email', $email)->first();
      }

      if (null === $user) {
         Log::warning('User ' . $email . ' was not found for SSO provider ' . $provider . ' from IP ' . request()->ip() . '.');
         abort(403);
      }

      if (null === $user->external_id && null !== $providerUserId) {
         $user->external_id = $providerUserId;
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

      $scopes = array_values(array_filter(array_map('trim', explode(',', (string)$configured))));

      if ('azure' === $provider && !in_array('User.Read', $scopes, true)) {
         $scopes[] = 'User.Read';
      }

      return $scopes;
   }

   protected static function providerLabel(string $provider): string
   {
      return match ($provider) {
         'azure' => (string)config('ledningssystemet.sso_azure_button_text', 'Microsoft Entra ID'),
         'google' => (string)config('ledningssystemet.sso_google_button_text', 'Google Workspace'),
         default => Str::title($provider),
      };
   }

   protected static function hasCredentials(mixed $clientId, mixed $clientSecret, mixed $redirect): bool
   {
      return is_string($clientId) && '' !== trim($clientId)
         && is_string($clientSecret) && '' !== trim($clientSecret)
         && is_string($redirect) && '' !== trim($redirect);
   }

   protected static function inferAzureClientIdFromLegacyAuthorizeUrl(): ?string
   {
      $authorizeUrl = (string)config('ledningssystemet.oauth_server_authorize_url', '');
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

      $candidate = trim((string)($segments[0] ?? ''));

      return '' === $candidate ? null : $candidate;
   }
}
