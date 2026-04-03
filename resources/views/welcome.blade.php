<!DOCTYPE html>
<html lang="sv">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Ledningssystemet') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <main class="mx-auto max-w-3xl px-6 py-16">
            <h1 class="text-2xl font-semibold">Du ar inloggad</h1>

            @if (session('oauth_success'))
                <p class="mt-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-green-800">{{ session('oauth_success') }}</p>
            @endif

            <p class="mt-4 text-gray-700">Valkommen till ledningssystemet.</p>

            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white">Logga ut</button>
            </form>
        </main>
    </body>
</html>
