<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Operations Preview</title>
    <style>
        body { font-family: Inter, Arial, sans-serif; background:#f5f7fb; color:#0b1f3a; margin:0; }
        .wrap { max-width:1100px; margin:0 auto; padding:3rem 1.5rem; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:1.5rem; margin-bottom:1rem; }
        h1 { font-size:2.75rem; margin:0 0 .75rem; }
        h2 { margin-top:0; color:#d17f00; letter-spacing:.25em; text-transform:uppercase; font-size:.9rem; }
        ul { line-height:1.8; color:#415472; }
        code { background:#eff4fb; padding:.15rem .35rem; border-radius:6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h2>Operations</h2>
        <h1>Backend operations preview</h1>
        <p>ئەم page ـە بۆ تاقیکردنەوەی backend readiness ـە، نەک customer-facing UI.</p>
    </div>
    <div class="card">
        <h2>Ready endpoints</h2>
        <ul>
            <li><code>GET /api/jobs</code> — jobs list</li>
            <li><code>POST /api/jobs</code> — create new job</li>
            <li><code>PATCH /api/jobs/{job}/status</code> — update status</li>
            <li><code>POST /api/jobs/{job}/payments</code> — record payment</li>
        </ul>
    </div>
</div>
</body>
</html>
