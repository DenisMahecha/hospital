<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment receipt {{ $payment->id }} | MediCore</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --ink: #16233b; --muted: #718096; --line: #e5eaf1; --accent: #176b87; --soft: #eef8fa; }
        * { box-sizing: border-box; }
        body.receipt-body { margin: 0; min-height: 100vh; background: #edf2f5; color: var(--ink); font-family: Georgia, 'Times New Roman', serif; }
        .receipt-shell { width: min(100% - 32px, 760px); margin: 40px auto; }
        .receipt-card { overflow: hidden; background: #fff; border: 1px solid #dfe6ed; border-radius: 16px; box-shadow: 0 18px 50px rgba(22, 35, 59, .12); }
        .receipt-top { height: 8px; background: linear-gradient(90deg, #176b87 0 72%, #d59b45 72% 100%); }
        .receipt-content { padding: 34px 42px 28px; }
        .receipt-header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; padding-bottom: 26px; border-bottom: 1px solid var(--line); }
        .brand-row { display: flex; align-items: center; gap: 13px; }
        .brand-mark { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 12px; background: var(--ink); color: #fff; font: 700 16px Arial, sans-serif; letter-spacing: 1px; }
        .brand-name { margin: 0; font: 700 24px Arial, sans-serif; letter-spacing: -.4px; }
        .receipt-subtitle { margin: 5px 0 0; color: var(--muted); font: 14px Arial, sans-serif; }
        .paid-badge { padding: 8px 13px; border: 1px solid #bde5d2; border-radius: 999px; background: #ecfaf2; color: #167044; font: 700 12px Arial, sans-serif; letter-spacing: 1px; }
        .receipt-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; padding: 22px 0; }
        .meta-item span, .section-label { display: block; margin-bottom: 6px; color: var(--muted); font: 11px Arial, sans-serif; letter-spacing: .08em; text-transform: uppercase; }
        .meta-item strong { font: 700 14px Arial, sans-serif; }
        .patient-box { display: flex; justify-content: space-between; gap: 20px; padding: 18px 20px; border: 1px solid #dcecef; border-radius: 12px; background: var(--soft); }
        .patient-name { margin: 0; font: 700 18px Arial, sans-serif; }
        .patient-number { margin: 5px 0 0; color: var(--muted); font: 13px Arial, sans-serif; }
        .details { margin-top: 24px; border-top: 1px solid var(--line); }
        .detail-row { display: flex; justify-content: space-between; gap: 20px; padding: 14px 0; border-bottom: 1px solid var(--line); font: 14px Arial, sans-serif; }
        .detail-row span { color: var(--muted); }
        .detail-row strong { text-align: right; text-transform: capitalize; }
        .amount-box { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-top: 24px; padding: 20px; border-radius: 12px; background: var(--ink); color: #fff; font-family: Arial, sans-serif; }
        .amount-box span { color: #b9c8da; font-size: 13px; }
        .amount-box strong { font-size: 25px; }
        .receipt-note { margin: 22px 0 0; color: var(--muted); text-align: center; font: 13px/1.6 Arial, sans-serif; }
        .receipt-footer { margin-top: 24px; padding-top: 17px; border-top: 1px dashed #cbd5df; color: var(--muted); text-align: center; font: 11px Arial, sans-serif; }
        .receipt-actions { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 24px; font-family: Arial, sans-serif; }
        .receipt-actions .button { cursor: pointer; border: 0; border-radius: 8px; padding: 10px 15px; background: var(--accent); color: #fff; text-decoration: none; font-size: 13px; }
        .receipt-actions .button-secondary { background: #e8eef3; color: var(--ink); }
        @media (max-width: 560px) { .receipt-shell { width: min(100% - 18px, 760px); margin: 12px auto; } .receipt-content { padding: 25px 20px 22px; } .receipt-header { gap: 12px; } .brand-name { font-size: 20px; } .patient-box, .amount-box { align-items: flex-start; flex-direction: column; } .amount-box strong { font-size: 23px; } }
        @media print { body.receipt-body { background: #fff; } .receipt-shell { width: 100%; margin: 0; } .receipt-card { border: 0; border-radius: 0; box-shadow: none; } .receipt-top { height: 5px; } .receipt-actions { display: none; } }
    </style>
</head>
<body class="receipt-body">
    <main class="receipt-shell">
        <section class="receipt-card">
            <div class="receipt-top"></div>
            <div class="receipt-content">
                <header class="receipt-header">
                    <div class="brand-row"><div class="brand-mark">MC</div><div><h1 class="brand-name">MediCore Hospital</h1><p class="receipt-subtitle">Official payment receipt</p></div></div>
                    <span class="paid-badge">PAID</span>
                </header>
                <div class="receipt-meta"><div class="meta-item"><span>Receipt number</span><strong>RCT-{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</strong></div><div class="meta-item"><span>Date and time</span><strong>{{ $payment->paid_at?->format('d M Y, H:i') }}</strong></div></div>
                <section class="patient-box"><div><span class="section-label">Received from</span><h2 class="patient-name">{{ $payment->invoice?->patient?->first_name }} {{ $payment->invoice?->patient?->last_name }}</h2><p class="patient-number">Patient ID: {{ $payment->invoice?->patient?->patient_number ?? 'N/A' }}</p></div><div class="meta-item"><span>Invoice</span><strong>{{ $payment->invoice?->invoice_number }}</strong></div></section>
                <div class="details"><div class="detail-row"><span>Payment method</span><strong>{{ str_replace('_', ' ', ucfirst($payment->payment_method)) }}</strong></div><div class="detail-row"><span>Reference</span><strong>{{ $payment->reference ?: 'N/A' }}</strong></div><div class="detail-row"><span>Received by</span><strong>{{ $payment->receivedBy?->name ?? 'MediCore cashier' }}</strong></div></div>
                <div class="amount-box"><span>Amount paid</span><strong>TZS {{ number_format($payment->amount, 2) }}</strong></div>
                <p class="receipt-note">Thank you. This receipt confirms that the payment above was received by MediCore Hospital.</p>
                <div class="receipt-footer">Keep this receipt for your records. Generated by MediCore Hospital Management System.</div>
                <div class="receipt-actions"><button class="button" type="button" onclick="window.print()">Print receipt</button><a class="button button-secondary" href="{{ route('payments.receipt.pdf', $payment) }}">Download PDF</a><a class="button button-secondary" href="{{ route('payments.index') }}">Back to payments</a></div>
            </div>
        </section>
    </main>
</body>
</html>
