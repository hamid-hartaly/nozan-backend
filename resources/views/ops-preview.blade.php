<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Operations Preview</title>
    <style>
        :root { font-family: Inter, Arial, sans-serif; --navy:#0b1f3a; --gold:#d8a21b; --surface:#f8fafc; --border:#e2e8f0; --muted:#475569; }
        * { box-sizing:border-box; }
        body { margin:0; background:#f1f5f9; color:#0f172a; }
        .layout { display:grid; min-height:100vh; grid-template-columns:280px 1fr 360px; }
        .sidebar { background:var(--navy); color:#fff; padding:24px 18px; }
        .brand { font-size:16px; line-height:1.6; }
        .brand strong { display:block; font-size:22px; }
        .nav { margin-top:32px; display:grid; gap:8px; }
        .nav a { color:#fff; text-decoration:none; padding:14px 16px; border-radius:14px; background:rgba(255,255,255,.08); }
        .nav a.active { background:rgba(255,255,255,.16); }
        .profile { margin-top:32px; border-radius:18px; background:rgba(255,255,255,.1); padding:16px; }
        .main { padding:32px; }
        .eyebrow { color:var(--gold); text-transform:uppercase; letter-spacing:.35em; font-size:12px; font-weight:700; }
        h1 { font-size:48px; margin:.25rem 0 0; letter-spacing:-.04em; }
        .muted { color:var(--muted); font-size:20px; line-height:1.7; max-width:820px; }
        .phone { margin-top:28px; background:#fff; border:1px solid var(--border); border-radius:32px; padding:24px; max-width:780px; box-shadow:0 20px 40px rgba(15,23,42,.06); }
        .frame { margin:0 auto; width:380px; border:14px solid #e5e7eb; border-radius:48px; padding:18px; background:#f8fafc; }
        .queue-top { background:var(--navy); color:#fff; border-radius:24px; padding:18px; }
        .search, .select { margin-top:14px; border:1px solid var(--border); border-radius:18px; background:#fff; padding:16px; color:#94a3b8; }
        .chips { display:flex; gap:10px; margin-top:14px; flex-wrap:wrap; }
        .chip { border-radius:999px; padding:10px 14px; background:#e2e8f0; color:#334155; font-weight:600; }
        .chip.active { background:var(--navy); color:#fff; }
        .metrics { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:18px; }
        .metric { background:#fff; border:1px solid var(--border); border-radius:20px; padding:14px; }
        .metric small { display:block; color:#64748b; text-transform:uppercase; letter-spacing:.2em; margin-bottom:8px; }
        .metric strong { font-size:36px; }
        .side { padding:32px 24px 32px 0; display:grid; gap:20px; }
        .panel { background:#fff; border:1px solid var(--border); border-radius:24px; padding:22px; }
        .panel h3 { margin:0 0 14px; text-transform:uppercase; letter-spacing:.25em; font-size:12px; color:#b45309; }
        .panel p, .panel li { color:#475569; line-height:1.8; font-size:16px; }
        @media (max-width: 1200px) { .layout { grid-template-columns:1fr; } .side { padding:0 32px 32px; } .sidebar { display:none; } }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">Nozan Service <strong>Control Panel</strong></div>
        <nav class="nav">
            <a class="active" href="#">Jobs</a>
            <a href="#">New Job</a>
            <a href="#">Inventory</a>
        </nav>
        <div class="profile">
            <strong>Ahmad</strong>
            <div style="opacity:.8;margin-top:4px;">Staff</div>
            <div style="margin-top:14px;height:56px;border-radius:14px;background:#fff;"></div>
        </div>
    </aside>

    <main class="main">
        <div class="eyebrow">Operations</div>
        <h1>Jobs List</h1>
        <p class="muted">Mobile-first operational queue with search, status filtering, technician routing, and fast job access for the service team.</p>

        <div class="phone">
            <div class="frame">
                <div class="queue-top">
                    <div style="font-size:12px;letter-spacing:.35em;text-transform:uppercase;opacity:.7;">Service desk</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                        <strong style="font-size:22px;">Jobs Queue</strong>
                        <span style="background:rgba(255,255,255,.16);padding:8px 14px;border-radius:999px;font-size:14px;">Today</span>
                    </div>
                </div>
                <div class="search">Search by customer, job ID, technician, or phone</div>
                <div class="select">All technicians</div>
                <div class="chips">
                    <div class="chip active">All</div>
                    <div class="chip">Pending</div>
                    <div class="chip">Repair</div>
                    <div class="chip">Finished</div>
                    <div class="chip">Out</div>
                </div>
                <div class="metrics">
                    <div class="metric"><small>Visible</small><strong>0</strong></div>
                    <div class="metric"><small>Assigned</small><strong style="color:#15803d;">0</strong></div>
                    <div class="metric"><small>Unassigned</small><strong style="color:#c2410c;">0</strong></div>
                </div>
            </div>
        </div>
    </main>

    <section class="side">
        <div class="panel">
            <h3>Access rules</h3>
            <p>Admin, Accountant, and Staff can open the jobs list.</p>
            <p>Admin and Staff are expected to operate on jobs from this screen.</p>
            <p>Accountant can inspect statuses and prices without operational editing.</p>
            <p>The queue now reads directly from the Laravel jobs API with technician assignment filtering.</p>
        </div>
        <div class="panel">
            <h3>Why this layout</h3>
            <ul>
                <li>The primary staff experience stays narrow, touch-friendly, and list-first.</li>
                <li>Status filters remain immediately visible without opening a modal.</li>
                <li>Search and technician routing are surfaced before job cards.</li>
            </ul>
        </div>
    </section>
</div>
</body>
</html>
