<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('auth.reset_password.title') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <main class="mx-auto max-w-md px-6 py-16">
            <h1 class="text-2xl font-semibold">{{ __('auth.reset_password.heading') }}</h1>

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4 rounded border border-gray-200 bg-white p-6">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-sm font-medium">{{ __('auth.login.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">{{ __('auth.reset_password.new_password') }}</label>
                    <input id="password" type="password" name="password" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium">{{ __('auth.reset_password.confirm_password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </div>

                <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white">{{ __('auth.reset_password.save') }}</button>
            </form>
        </main>
    </body>
</html>

