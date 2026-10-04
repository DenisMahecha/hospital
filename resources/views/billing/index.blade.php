@extends('layouts.app')
@section('title', 'Billing')
@section('eyebrow', 'Revenue cycle')
@section('content')
<section class="page-heading"><div><h1>Billing</h1><p>Keep invoices, balances, and payer activity moving.</p></div><span class="badge badge-success">Finance desk</span></section>
@if(auth()->user()->hasAnyRole(['super-admin', 'cashier']))
<section class="panel"><div class="panel-heading"><div><h2>Generate from patient journey</h2><p>Automatically bill completed consultation, lab, radiology, medicines, emergency and IPD services.</p></div></div><form method="POST" action="{{ route('billing.journey') }}" class="form-grid">@csrf<div class="field"><label for="journey_patient_search">Search patient</label><input id="journey_patient_search" type="search" list="billing-patient-suggestions" autocomplete="off" placeholder="Name, patient number, or phone" data-patient-search="journey_patient_id"><input id="journey_patient_id" type="hidden" name="patient_id" required></div><div class="form-actions"><button class="button" type="submit">Calculate and generate invoice</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Create invoice</h2><p>Generate a bill for a patient service before collecting payment.</p></div></div><form method="POST" action="{{ route('billing.store') }}" class="form-grid">@csrf<div class="field"><label for="invoice_patient_search">Search patient</label><input id="invoice_patient_search" type="search" list="billing-patient-suggestions" autocomplete="off" placeholder="Name, patient number, or phone" data-patient-search="invoice_patient_id"><input id="invoice_patient_id" type="hidden" name="patient_id" required></div><div class="field field-wide"><label for="service_description">Service / description</label><textarea id="service_description" name="service_description" required placeholder="Consultation, laboratory test, medicine, bed fee"></textarea></div><div class="field"><label for="total_amount">Amount (TZS)</label><input id="total_amount" name="total_amount" type="number" min="0.01" step="0.01" required></div><div class="form-actions field-wide"><button class="button" type="submit">Create invoice</button></div></form></section>
<datalist id="billing-patient-suggestions"></datalist>
<script>
	(() => {
		const list = document.getElementById('billing-patient-suggestions');
		const inputs = document.querySelectorAll('[data-patient-search]');
		if (!list || !inputs.length) return;
		let timer;
		let suggestions = [];
		inputs.forEach((input) => {
			input.addEventListener('input', () => {
				document.getElementById(input.dataset.patientSearch).value = '';
				clearTimeout(timer);
				const query = input.value.trim();
				if (query.length < 2) {
					list.replaceChildren();
					return;
				}
				timer = setTimeout(async () => {
					const response = await fetch('{{ route('billing.patient-suggestions') }}?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' } });
					if (!response.ok) return;
					suggestions = await response.json();
					list.replaceChildren(...suggestions.map((patient) => {
						const option = document.createElement('option');
						option.value = patient.label;
						return option;
					}));
				}, 180);
			});
			input.addEventListener('change', () => {
				const selected = suggestions.find((patient) => patient.label === input.value || patient.name === input.value);
				if (selected) {
					input.value = selected.label;
					document.getElementById(input.dataset.patientSearch).value = selected.id;
					loadPatientServices(selected.id);
				}
			});
		});
		async function loadPatientServices(patientId) {
			const description = document.getElementById('service_description');
			if (!description || !patientId) return;
			const response = await fetch('{{ route('billing.patient-services') }}?patient_id=' + encodeURIComponent(patientId), { headers: { Accept: 'application/json' } });
			if (!response.ok) return;
			const services = await response.json();
			description.value = services.description || '';
			const amount = document.getElementById('total_amount');
			if (amount && services.total > 0) amount.value = services.total;
		}
	})();
</script>
@endif
<section class="panel"><div class="panel-heading"><div><h2>Invoice register</h2><p>Most recent patient invoices with calculated service breakdown.</p></div></div>@if($invoices->isEmpty())<div class="empty-state"><strong>No invoices yet</strong>Invoices will appear here after services are billed.</div>@else<div class="table-wrap"><table class="data-table"><thead><tr><th>Invoice</th><th>Patient</th><th>Services</th><th>Amount</th><th>Status</th></tr></thead><tbody>@foreach($invoices as $invoice)<tr><td><strong>{{ $invoice->invoice_number }}</strong></td><td>{{ $invoice->patient?->first_name ?? 'Patient record' }}</td><td>@if($invoice->items->isNotEmpty())<ul style="margin:0;padding-left:16px">@foreach($invoice->items as $item)<li>{{ $item->description }}: TZS {{ number_format($item->total_price) }}</li>@endforeach</ul>@else{{ $invoice->service_description ?: 'Manual invoice' }}@endif</td><td>TZS {{ number_format($invoice->total_amount) }}</td><td><span class="badge {{ $invoice->status === 'paid' ? 'badge-success' : 'badge-warning' }}">{{ $invoice->status }}</span></td></tr>@endforeach</tbody></table></div>@endif</section>
@endsection
