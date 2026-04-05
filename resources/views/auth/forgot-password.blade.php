<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('auth.forgot_password.title') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <main class="mx-auto max-w-md px-6 py-16">
            <h1 class="text-2xl font-semibold">{{ __('auth.forgot_password.heading') }}</h1>

            @if (session('status'))
                <p class="mt-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4 rounded border border-gray-200 bg-white p-6">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium">{{ __('auth.login.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white">{{ __('auth.forgot_password.send_link') }}</button>
                <a href="{{ route('login') }}" class="inline-block text-sm text-blue-700 underline">{{ __('auth.forgot_password.back_to_login') }}</a>
            </form>
        </main>
    </body>
</html>

