<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $job->job_code }}</title>
    <style>
        :root {
            --bg: #f4f6fb;
            --ink: #0f172a;
            --muted: #475569;
            --line: #dbe3ef;
            --brand: #0f4c81;
            --brand-soft: #e8f1fb;
            --ok: #0f7a4a;
            --ok-soft: #dff7ea;
            --warn: #9a3412;
            --warn-soft: #fff0e6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: radial-gradient(circle at 10% 10%, #eef5ff 0%, var(--bg) 45%, #f6f8fc 100%);
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            padding: 24px;
        }

        .invoice-shell {
            max-width: 940px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 16px 45px rgba(15, 23, 42, 0.09);
        }

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
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-badge {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: linear-gradient(140deg, #ffffff 0%, #dbeafe 100%);
            display: grid;
            place-items: center;
            color: #0f4c81;
            font-size: 26px;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px rgba(15, 76, 129, 0.18);
        }

        .brand-wrap h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 0.01em;
        }

        .brand-wrap p {
            margin: 8px 0 0;
            opacity: 0.92;
            font-size: 13px;
        }

        .invoice-meta {
            min-width: 240px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            padding: 12px 14px;
        }

        .meta-line {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 4px 0;
        }

        .meta-line strong {
            font-weight: 700;
        }

        .content {
            padding: 20px 22px 24px;
        }

        .grid-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
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
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .chip.brand { background: var(--brand-soft); color: var(--brand); }
        .chip.ok { background: var(--ok-soft); color: var(--ok); }
        .chip.warn { background: var(--warn-soft); color: var(--warn); }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        thead th {
            text-align: left;
            background: #eef3fb;
            border-bottom: 1px solid var(--line);
            color: #1e3a5f;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 10px;
        }

        tbody td {
            border-bottom: 1px solid #edf1f7;
            padding: 9px 10px;
            font-size: 13px;
        }

        tbody tr:nth-child(even) {
            background: #fbfdff;
        }

        .text-right { text-align: right; }

        .money { font-weight: 700; color: #0f172a; }

        .totals {
            width: 360px;
            margin-left: auto;
            margin-top: 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
        }

        .totals td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid #edf1f7;
        }

        .totals tr:last-child td { border-bottom: none; }

        .totals .summary { font-weight: 700; background: #f1f6ff; }

        .totals .balance-ok { color: var(--ok); font-weight: 800; }
        .totals .balance-due { color: #b42318; font-weight: 800; }

        .footer-note {
            margin-top: 14px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }

        .actions {
            margin-top: 14px;
            text-align: right;
        }

        .utility-grid {
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 14px;
            margin-top: 14px;
        }

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
            color: #47607f;
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
