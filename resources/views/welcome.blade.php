<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Nozan Backend') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-100">
        <main class="mx-auto flex min-h-screen max-w-4xl flex-col justify-center gap-8 px-6 py-16">
            <div class="space-y-4">
                <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Nozan Service System</p>
                <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">Laravel backend is running.</h1>
                <p class="max-w-2xl text-base leading-7 text-stone-300 sm:text-lg">
                    This service provides the Filament admin panel and API endpoints for the Nozan platform.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <a
                    href="{{ url('/admin/login') }}"
                    class="inline-flex items-center justify-center rounded-md bg-amber-400 px-5 py-3 text-sm font-medium text-stone-950 transition hover:bg-amber-300"
                >
                    Open Admin Panel
                </a>
                <a
                    href="{{ url('/api/auth/me') }}"
                    class="inline-flex items-center justify-center rounded-md border border-stone-700 px-5 py-3 text-sm font-medium text-stone-100 transition hover:border-stone-500 hover:bg-stone-900"
                >
                    Test Auth Endpoint
                </a>
            </div>
        </main>
    </body>
</html>
