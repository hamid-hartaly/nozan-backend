<!DOCTYPE html>
            --bg: #090d17;
            --bg-soft: #131a2d;
            --ink: #f5f7fb;
            --muted: rgba(255,255,255,0.68);
            --line: rgba(202, 164, 90, 0.28);
            --brand: #0b1020;
            --brand-soft: rgba(255,255,255,0.06);
            --gold: #caa45a;
            --ok: #8be2b3;
            --ok-soft: rgba(52, 211, 153, 0.15);
            --warn: #f4c07a;
            --warn-soft: rgba(202, 164, 90, 0.14);
            --line: #dbe3ef;
            --brand: #0f4c81;
            --brand-soft: #e8f1fb;
            --ok: #0f7a4a;
            background: radial-gradient(circle at 10% 10%, #1c2441 0%, #0b1020 48%, var(--bg) 100%);
            --warn: #9a3412;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: linear-gradient(180deg, rgba(13, 17, 31, 0.98) 0%, rgba(14, 20, 36, 0.98) 100%);
            border: 1px solid var(--line);
            border-radius: 26px;
            padding: 24px;
            box-shadow: 0 26px 80px rgba(0, 0, 0, 0.45);

        .invoice-shell {
            max-width: 940px;
            position: relative;
            background: linear-gradient(135deg, #0b1020 0%, #121a2f 62%, #151f37 100%);
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 16px 45px rgba(15, 23, 42, 0.09);
        }
            border-bottom: 1px solid var(--line);
        }

        .topbar::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top left, rgba(202, 164, 90, 0.18), transparent 28%),
                radial-gradient(circle at bottom right, rgba(255,255,255,0.08), transparent 20%);
            pointer-events: none;

        .topbar {
            background: linear-gradient(120deg, #0f4c81 0%, #145da0 55%, #1c75bc 100%);
            color: #ffffff;
            padding: 26px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .brand-row {
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #f4d896 0%, #caa45a 35%, #4c3311 100%);
            gap: 12px;
        }
            color: #0b1020;
        .logo-badge {
            width: 54px;
            box-shadow: 0 0 0 2px rgba(202, 164, 90, 0.22), inset 0 0 0 1px rgba(255,255,255,0.25);
            border-radius: 14px;
            background: linear-gradient(140deg, #ffffff 0%, #dbeafe 100%);
            display: grid;
            place-items: center;
            color: #0f4c81;
            font-size: 26px;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px rgba(15, 76, 129, 0.18);
        }

            color: rgba(255,255,255,0.72);
            margin: 0;
            font-size: 28px;
            letter-spacing: 0.01em;
        }

            position: relative;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--line);
            border-radius: 18px;
            font-size: 13px;
            backdrop-filter: blur(8px);
        }

        .invoice-meta {
            min-width: 240px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            padding: 12px 14px;
        }

        .meta-line {
            color: #f2d594;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 4px 0;
            background: radial-gradient(circle at top, #1a223b 0%, #0e1424 65%, #0a0f1b 100%);
        }

        .meta-line strong {
            font-weight: 700;
            border-radius: 20px;

            background: linear-gradient(180deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.03) 100%);
            padding: 20px 22px 24px;
        }

        .grid-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            color: var(--gold);
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            color: var(--muted);
            background: #ffffff;
        }

        .panel-title {
            margin: 0 0 10px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--muted);
            font-weight: 700;
        }

        .line {
            margin: 5px 0;
            font-size: 13px;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .chip {
            border-radius: 999px;
            padding: 5px 10px;
                background: #111a30;
                border-bottom: 1px solid var(--line);
                color: #f3d79c;
        }

        .chip.brand { background: var(--brand-soft); color: var(--brand); }
        .chip.ok { background: var(--ok-soft); color: var(--ok); }
        .chip.warn { background: var(--warn-soft); color: var(--warn); }

        table {
                border-bottom: 1px solid rgba(202, 164, 90, 0.12);
            border-collapse: collapse;
            margin-top: 10px;
                color: rgba(255,255,255,0.8);
        }

        thead th {
                background: rgba(0, 0, 0, 0.12);
            background: #eef3fb;
            border-bottom: 1px solid var(--line);
            color: #1e3a5f;
            .money { color: #f3d79c; }
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 10px;
        }

                border-radius: 18px;
            border-bottom: 1px solid #edf1f7;
                background: linear-gradient(180deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.03) 100%);
            padding: 9px 10px;
            font-size: 13px;
        }

        tbody tr:nth-child(even) {
                border-bottom: 1px solid rgba(202, 164, 90, 0.12);
                color: rgba(255,255,255,0.75);
        }

        .text-right { text-align: right; }

        .money { font-weight: 700; color: #0f172a; }
            .totals .balance-due { color: #f3d79c; font-weight: 800; }
        .totals {
            width: 360px;
            margin-left: auto;
            margin-top: 14px;
                color: rgba(255,255,255,0.58);
            border-radius: 12px;
            overflow: hidden;
        }

                border: 1px dashed rgba(202, 164, 90, 0.36);
                border-radius: 18px;
            font-size: 13px;
                background: rgba(255,255,255,0.04);
        }

        .totals tr:last-child td { border-bottom: none; }

                color: rgba(255,255,255,0.62);

        .totals .balance-ok { color: var(--ok); font-weight: 800; }
        .totals .balance-due { color: #b42318; font-weight: 800; }

        .footer-note {
            margin-top: 14px;
            font-size: 12px;
                border: 1px solid rgba(202, 164, 90, 0.25);
            text-align: center;
        }

        .actions {
            margin-top: 14px;
                border: 1px solid rgba(202, 164, 90, 0.4);
                background: linear-gradient(180deg, #d4af67 0%, #b3873e 100%);
                color: #0b1020;
                border-radius: 14px;
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 14px;
            margin-top: 14px;
        }
            .print-btn:hover { background: linear-gradient(180deg, #ddb978 0%, #bc9048 100%); }
        .verify-box {
            border: 1px dashed #b6c4d8;
            border-radius: 12px;
            padding: 10px;
            background: #f8fbff;
            text-align: center;
        }

        .verify-box p {
            margin: 5px 0 0;
            font-size: 11px;
                            <p>Professional Repair, Trusted Parts, Transparent Billing</p>
            line-height: 1.45;
            word-break: break-all;
        }

        .qr-img {
            width: 120px;
            height: 120px;
            border: 1px solid #d6e0ec;
            border-radius: 10px;
            background: #fff;
        }

        .print-btn {
            border: 1px solid #0f4c81;
            background: #0f4c81;
            color: #fff;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .print-btn:hover { background: #0d406c; }

        @media (max-width: 760px) {
            body { padding: 10px; }
            .topbar { flex-direction: column; }
            .grid-two { grid-template-columns: 1fr; }
            .totals { width: 100%; }
            .utility-grid { grid-template-columns: 1fr; }
        }

        @media print {
            body { background: #fff; padding: 0; }
            .invoice-shell { box-shadow: none; border-radius: 0; border: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    @php
        $items = collect($job->invoice_items ?? []);
        $subtotal = (float) $items->sum(fn ($item) => (float) ($item['line_total_iqd'] ?? 0));
        $discount = (float) ($job->invoice_discount_iqd ?? 0);
        $tax = (float) ($job->invoice_tax_iqd ?? 0);
        $total = (float) ($job->final_price_iqd ?? 0);
        $paid = (float) $job->payments->sum('amount_iqd');
        $balance = max(0, $total - $paid);
        $isPaid = $balance <= 0.0;
        $verificationUrl = route('invoices.print', ['serviceJob' => $job->id]);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($verificationUrl);
    @endphp

    <div class="invoice-shell">
        <header class="topbar">
            <div class="brand-wrap">
                <div class="brand-row">
                    <div class="logo-badge">TV</div>
                    <div>
                        <h1>Nozan TV Repair Center</h1>
                        <p>Professional Repair, Trusted Parts, Transparent Billing</p>
                    </div>
                </div>
            </div>

            <div class="invoice-meta">
                <div class="meta-line"><span>Invoice No.</span><strong>{{ $job->job_code }}</strong></div>
                <div class="meta-line"><span>Issue Date</span><strong>{{ now()->format('Y-m-d H:i') }}</strong></div>
                <div class="meta-line"><span>Status</span><strong>{{ strtoupper((string) $job->status) }}</strong></div>
            </div>
        </header>

        <main class="content">
            <section class="grid-two">
                <article class="panel">
                    <h3 class="panel-title">Customer Details</h3>
                    <p class="line"><strong>Name:</strong> {{ $job->customer_name }}</p>
                    <p class="line"><strong>Phone:</strong> {{ $job->customer_phone }}</p>
                    <p class="line"><strong>Address:</strong> {{ $job->customer?->address ?: 'N/A' }}</p>
                </article>

                <article class="panel">
                    <h3 class="panel-title">Repair Details</h3>
                    <p class="line"><strong>TV Model:</strong> {{ $job->tv_model ?: 'N/A' }}</p>
                    <p class="line"><strong>Category:</strong> {{ $job->category ?: 'N/A' }}</p>
                    <p class="line"><strong>Issue:</strong> {{ $job->issue ?: 'N/A' }}</p>
                    <div class="chips">
                        <span class="chip brand">SERVICE ORDER</span>
                        <span class="chip {{ $isPaid ? 'ok' : 'warn' }}">{{ $isPaid ? 'PAID' : 'PAYMENT DUE' }}</span>
                    </div>
                </article>
            </section>

            <section class="panel">
                <h3 class="panel-title">Invoice Items</h3>

                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item['item_name'] ?? '-' }}</td>
                                <td>{{ strtoupper((string) ($item['item_type'] ?? '-')) }}</td>
                                <td class="text-right">{{ number_format((float) ($item['quantity'] ?? 0), 2) }}</td>
                                <td class="text-right">{{ number_format((float) ($item['unit_price_iqd'] ?? 0), 0) }} IQD</td>
                                <td class="text-right money">{{ number_format((float) ($item['line_total_iqd'] ?? 0), 0) }} IQD</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No invoice items yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <table class="totals">
                    <tr><td>Subtotal</td><td class="text-right">{{ number_format($subtotal, 0) }} IQD</td></tr>
                    <tr><td>Discount</td><td class="text-right">{{ number_format($discount, 0) }} IQD</td></tr>
                    <tr><td>Tax</td><td class="text-right">{{ number_format($tax, 0) }} IQD</td></tr>
                    <tr class="summary"><td>Total</td><td class="text-right">{{ number_format($total, 0) }} IQD</td></tr>
                    <tr><td>Paid</td><td class="text-right">{{ number_format($paid, 0) }} IQD</td></tr>
                    <tr>
                        <td>Balance</td>
                        <td class="text-right {{ $isPaid ? 'balance-ok' : 'balance-due' }}">{{ number_format($balance, 0) }} IQD</td>
                    </tr>
                </table>
            </section>

            <div class="utility-grid">
                <div class="footer-note" style="text-align: left; margin-top: 0;">
                    Thank you for trusting Nozan TV Repair Center. Keep this invoice for warranty and support follow-up.
                    Reference: {{ $job->job_code }}
                </div>
                <aside class="verify-box">
                    <img src="{{ $qrUrl }}" alt="Invoice QR" class="qr-img">
                    <p>Scan to open this invoice</p>
                    <p>{{ $verificationUrl }}</p>
                </aside>
            </div>

            <div class="actions">
                <button class="print-btn" onclick="window.print()">Print Invoice</button>
            </div>
        </main>
    </div>
</body>
</html>
