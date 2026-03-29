<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Nozan Backend') }}</title>

        <style>
            :root {
                color-scheme: dark;
                font-family: Arial, Helvetica, sans-serif;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                background: #111827;
                color: #f9fafb;
            }

            main {
                max-width: 960px;
                margin: 0 auto;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 24px;
                padding: 32px 24px;
            }

            .eyebrow {
                margin: 0;
                color: #fbbf24;
                text-transform: uppercase;
                letter-spacing: 0.3em;
                font-size: 12px;
            }

            h1 {
                margin: 0;
                font-size: 48px;
                line-height: 1.1;
            }

            p {
                margin: 0;
                color: #d1d5db;
                font-size: 18px;
                line-height: 1.7;
                max-width: 720px;
            }

            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 14px 20px;
                border-radius: 8px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 700;
                transition: opacity 0.2s ease;
            }

            .button:hover {
                opacity: 0.9;
            }

            .button-primary {
                background: #fbbf24;
                color: #111827;
            }

            .button-secondary {
                border: 1px solid #374151;
                color: #f9fafb;
                background: transparent;
            }
        </style>
    </head>
    <body>
        <main>
            <div>
                <p class="eyebrow">Nozan Service System</p>
                <h1>Laravel backend is running.</h1>
                <p>
                    This service provides the Filament admin panel and API endpoints for the Nozan platform.
                </p>
            </div>

            <div class="actions">
                <a
                    href="{{ url('/admin/login') }}"
                    class="button button-primary"
                >
                    Open Admin Panel
                </a>
                <a
                    href="{{ url('/api/auth/me') }}"
                    class="button button-secondary"
                >
                    Test Auth Endpoint
                </a>
            </div>
        </main>
    </body>
</html>
