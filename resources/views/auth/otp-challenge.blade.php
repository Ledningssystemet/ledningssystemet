<!DOCTYPE html>
<html lang="sv">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verifiera OTP</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <main class="mx-auto max-w-md px-6 py-16">
            <h1 class="text-2xl font-semibold">Verifiera engangskod</h1>
            <p class="mt-3 text-sm text-gray-700">Ange den sexsiffriga kod som skickats till din e-post. Koden galler i {{ $ttlMinutes }} minuter.</p>

            @if (session('status'))
                <p class="mt-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('otp.verify') }}" class="mt-6 space-y-4 rounded border border-gray-200 bg-white p-6">
                @csrf

                <div>
                    <label for="otp" class="block text-sm font-medium">OTP-kod</label>
                    <input id="otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    @error('otp')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white">Verifiera och logga in</button>
            </form>

            <form method="POST" action="{{ route('otp.resend') }}" class="mt-4">
                @csrf
                <button type="submit" class="text-sm text-blue-700 underline">Skicka ny kod</button>
            </form>
        </main>
    </body>
</html>

