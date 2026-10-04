@extends('layouts.app')
@section('title', 'Payments')
@section('eyebrow', 'Revenue cycle')
@section('content')
@if(auth()->user()->hasAnyRole(['super-admin', 'cashier']))
<section class="panel"><div class="panel-heading"><div><h2>Daily cashier reconciliation</h2><p>Compare declared cash with completed payments.</p></div></div><form method="POST" action="{{ route('reconciliation.store') }}" class="form-grid">@csrf<div class="field"><label for="business-date">Business date</label><input id="business-date" type="date" name="business_date" value="{{ $businessDate }}" required></div><div class="field"><label for="declared-total">Declared total</label><input id="declared-total" name="declared_total" type="number" step="0.01" min="0" value="{{ number_format((float) $systemTotal, 2, '.', '') }}" required></div><div class="field field-wide"><label>Notes</label><textarea name="notes"></textarea></div><div class="form-actions field-wide"><button class="button" type="submit">Close cashier day</button></div></form></section>
<script>
	(() => {
		const date = document.getElementById('business-date');
		const total = document.getElementById('declared-total');
		if (!date || !total) return;
		date.addEventListener('change', async () => {
			const response = await fetch('{{ route('payments.system-total') }}?business_date=' + encodeURIComponent(date.value), { headers: { Accept: 'application/json' } });
			if (!response.ok) return;
			const data = await response.json();
			total.value = Number(data.total || 0).toFixed(2);
		});
	})();
</script>
@endif
<section class="page-heading"><div><h1>Payments</h1><p>Record collections and keep invoice balances accurate.</p></div><span class="badge badge-success">Cashier workspace</span></section>
<section class="dashboard-grid"><article class="panel"><div class="panel-heading"><div><h2>Outstanding invoices</h2><p>Collect against an invoice and update its status.</p></div></div>@if($invoices->isEmpty())<div class="empty-state"><strong>No invoices available</strong>Invoices will appear here when billing records are created.</div>@else<div class="table-wrap"><table class="data-table"><thead><tr><th>Invoice</th><th>Patient</th><th>Balance</th><th>Status</th></tr></thead><tbody>@foreach($invoices as $invoice)@php $balance = max(0, (float) $invoice->total_amount - (float) ($invoice->payments_sum_amount ?? 0)); @endphp<tr><td><strong>{{ $invoice->invoice_number }}</strong></td><td>{{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</td><td>TZS {{ number_format($balance) }}</td><td><span class="badge {{ $balance <= 0 ? 'badge-success' : 'badge-warning' }}">{{ $invoice->status }}</span></td></tr>@endforeach</tbody></table></div>@endif</article>
<article class="panel"><div class="panel-heading"><div><h2>Record payment</h2><p>Apply a collection to an invoice.</p></div></div><form method="POST" action="{{ route('payments.store') }}">@csrf<div class="field"><label for="invoice_id">Invoice</label><select id="invoice_id" name="invoice_id" required><option value="">Select invoice</option>@foreach($invoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} - {{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</option>@endforeach</select></div><div class="field" style="margin-top:14px"><label for="amount">Amount (TZS)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required></div><div class="field" style="margin-top:14px"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method" required><option value="cash">Cash</option><option value="card">Card</option><option value="mobile_money">Mobile money</option><option value="insurance">Insurance</option></select></div><div class="field" style="margin-top:14px"><label for="reference">Reference</label><input id="reference" name="reference" placeholder="Receipt or transaction number"></div><div class="form-actions"><button class="button" type="submit">Record payment</button></div></form></article></section>
<section class="panel" style="margin-top:18px"><div class="panel-heading"><div><h2>Recent collections</h2><p>Latest completed payments and printable receipts.</p></div></div>@forelse($payments as $payment)<div class="quick-link payment-row"><div>{{ $payment->invoice?->invoice_number }} - TZS {{ number_format($payment->amount) }}<span>{{ $payment->payment_method }} / {{ $payment->paid_at?->format('d M Y, H:i') }}</span></div><a class="button button-secondary" href="{{ route('payments.receipt', $payment) }}">View receipt</a></div>@empty<div class="empty-state"><strong>No payments recorded</strong>Completed collections will appear here.</div>@endforelse</section>
@endsection
