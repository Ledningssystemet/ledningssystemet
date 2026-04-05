<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('auth.login.title') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <main class="mx-auto max-w-md px-6 py-16">
            <h1 class="text-2xl font-semibold">{{ __('auth.login.title') }}</h1>

            @if (session('status'))
                <p class="mt-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            @if (session('oauth_error'))
                <p class="mt-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('oauth_error') }}</p>
            @endif

            @if ($showOauthButton)
                <a href="{{ route('oauth.redirect') }}" class="mt-6 inline-block w-full rounded bg-blue-700 px-4 py-2 text-center text-sm font-medium text-white">
                    {{ __('auth.login.workplace_account') }}
                </a>
            @endif

            @if ($showPasswordForm)
                <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4 rounded border border-gray-200 bg-white p-6">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium">{{ __('auth.login.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium">{{ __('auth.login.password') }}</label>
                        <input id="password" type="password" name="password" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        {{ __('auth.login.remember_me') }}
                    </label>

                    @if ($mfaEnabled && ! $mfaEnforced)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="use_otp" value="1" @checked(old('use_otp'))>
                            {{ __('auth.login.use_otp') }}
                        </label>
                    @endif

                    @if ($mfaEnforced)
                        <p class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            {{ __('auth.login.mfa_required') }}
                        </p>
                    @endif

                    <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white">{{ __('auth.login.submit') }}</button>

                    <a href="{{ route('password.request') }}" class="inline-block text-sm text-blue-700 underline">{{ __('auth.login.forgot_password') }}</a>
                </form>
            @endif
        </main>
    </body>
</html>

