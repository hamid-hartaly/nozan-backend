<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Nozan Backend') }}</title>
        <style>
            :root { color-scheme: light; font-family: Inter, Arial, sans-serif; }
            * { box-sizing: border-box; }
            body { margin: 0; background: #f5f7fb; color: #0b1f3a; }
            main { max-width: 1200px; margin: 0 auto; min-height: 100vh; display: grid; grid-template-columns: minmax(0,1.35fr) minmax(320px,0.85fr); gap: 2rem; align-items: center; padding: 3rem 1.5rem; }
            .eyebrow { color: #d8a21b; text-transform: uppercase; letter-spacing: .35em; font-size: .875rem; font-weight: 700; margin: 0 0 1rem; }
            h1 { font-size: clamp(3rem, 7vw, 5.5rem); line-height: .95; margin: 0 0 1.5rem; }
            p { font-size: 1.25rem; line-height: 1.7; color: #415472; margin: 0 0 1rem; }
            .actions { display:flex; flex-wrap:wrap; gap: .875rem; margin-top: 2rem; }
            .button { display:inline-flex; align-items:center; justify-content:center; padding: 1rem 1.25rem; border-radius: 14px; text-decoration:none; font-weight:700; }
            .primary { background:#0b1f3a; color:white; }
            .secondary { border:1px solid #dbe4ef; color:#0b1f3a; background:white; }
            .panel { background:white; border:1px solid #e2e8f0; border-radius: 28px; padding: 1.5rem; box-shadow: 0 12px 40px rgba(15, 23, 42, .06); }
            .stack { display:grid; gap: 1rem; }
            .card { border:1px solid #e2e8f0; border-radius: 22px; padding: 1.25rem; background:#fff; }
            .card h3 { margin:.25rem 0 .5rem; font-size: 1rem; }
            .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
            ul { color:#415472; line-height:1.7; padding-left:1.1rem; }
            @media (max-width: 900px) { main { grid-template-columns:1fr; } }
        </style>
    </head>
    <body>
        <main>
            <section>
                <p class="eyebrow">Nozan Service Center</p>
                <h1>Backend control, admin access, and API foundation in one Laravel workspace.</h1>
                <p>
                    ئەم backend ـە بنەمای Filament admin panel و API authentication و operation module ـە. بۆ گۆڕینی ڕووکاری customer/staff بەشی frontend پێویستە پێوەبەسترێت.
                </p>
                <p>
                    Jobs, customers, payments, inventory, and reporting endpoints ئێستا لە هەمان workspace ـدا ئامادەن بۆ تاقیکردنەوە.
                </p>
                <div class="actions">
                    <a href="{{ url('/admin') }}" class="button primary">Open admin panel</a>
                    <a href="{{ url('/ops-preview') }}" class="button secondary">Open operations preview</a>
                    <a href="{{ url('/api/jobs') }}" class="button secondary">Test jobs endpoint</a>
                </div>
            </section>
            <aside class="panel stack">
                <div class="card">
                    <h3>Admin panel</h3>
                    <p>Filament dashboard لە <span class="mono">/admin</span> بۆ back-office modules.</p>
                </div>
                <div class="card">
                    <h3>Authentication</h3>
                    <p>Sanctum endpoints: <span class="mono">/api/auth/login</span>, <span class="mono">/api/auth/me</span>, <span class="mono">/api/auth/logout</span>.</p>
                </div>
                <div class="card">
                    <h3>Next build stages</h3>
                    <ul>
                        <li>Jobs intake and timeline</li>
                        <li>Customers, payments, and debt tracking</li>
                        <li>Inventory visibility and low-stock view</li>
                    </ul>
                </div>
            </aside>
        </main>
    </body>
</html>
