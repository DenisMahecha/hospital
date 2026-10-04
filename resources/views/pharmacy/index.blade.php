@extends('layouts.app')
@section('title', 'Pharmacy')
@section('eyebrow', 'Medication')
@section('content')
<section class="page-heading"><div><h1>Pharmacy</h1><p>Medication stock, availability, and dispensing readiness.</p></div><span class="badge badge-success">Open today</span></section>
<form method="GET" class="panel form-grid"><div class="field"><label for="pharmacy-search">Search medicine or prescription</label><input id="pharmacy-search" name="q" value="{{ $search }}" placeholder="Name, category, or prescription number"></div><div class="form-actions"><button class="button button-secondary" type="submit">Search</button></div></form>
@if($lowStockDrugs->isNotEmpty() || $expiringDrugs->isNotEmpty())<section class="dashboard-grid">@if($lowStockDrugs->isNotEmpty())<article class="panel"><div class="panel-heading"><div><h2>Low stock alert</h2><p>Medicines below the reorder threshold.</p></div></div><ul>@foreach($lowStockDrugs as $drug)<li>{{ $drug->name }}: {{ $drug->stock_quantity }} units</li>@endforeach</ul></article>@endif @if($expiringDrugs->isNotEmpty())<article class="panel"><div class="panel-heading"><div><h2>Expiry alert</h2><p>Medicines expiring within 90 days.</p></div></div><ul>@foreach($expiringDrugs as $drug)<li>{{ $drug->name }}: {{ $drug->expiry_date->format('d M Y') }}</li>@endforeach</ul></article>@endif</section>@endif
@if(auth()->user()->hasAnyRole(['super-admin', 'pharmacist']))
<section class="panel"><div class="panel-heading"><div><h2>Dispense medicine</h2><p>Select a patient; the prescribed medicine and remaining quantity will be filled automatically.</p></div></div><form method="POST" action="{{ route('pharmacy.dispense') }}" class="form-grid">@csrf<div class="field"><label for="dispense_patient_id">Patient</label><select id="dispense_patient_id" required><option value="">Select patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->patient_number }})</option>@endforeach</select></div><div class="field"><label for="prescription_id">Prescription</label><select name="prescription_id" id="prescription_id"><option value="">Walk-in / manual issue</option>@foreach($prescriptions as $prescription)<option value="{{ $prescription->id }}" data-patient-id="{{ $prescription->patient_id }}" data-medication-name="{{ $prescription->medication_name }}" data-quantity-remaining="{{ $prescription->quantity_prescribed !== null ? max(0, $prescription->quantity_prescribed - $prescription->quantity_dispensed) : '' }}">{{ $prescription->prescription_number }} - {{ $prescription->patient?->first_name }} {{ $prescription->patient?->last_name }} - {{ $prescription->medication_name }}</option>@endforeach</select></div><div class="field"><label for="drug_id">Medicine</label><select name="drug_id" id="drug_id" required><option value="">Select medicine</option>@foreach($drugs as $drug)<option value="{{ $drug->id }}" data-drug-name="{{ $drug->name }}">{{ $drug->name }} ({{ $drug->stock_quantity }} available)</option>@endforeach</select></div><div class="field"><label for="dispense_quantity">Quantity</label><input id="dispense_quantity" name="quantity" type="number" min="1" required></div><div class="field"><label>Reference</label><input name="reference" placeholder="Prescription number or sale reference"></div><div class="field field-wide"><small>Patient selection fills the matching prescription, medicine, and remaining quantity automatically.</small></div><div class="form-actions field-wide"><button class="button" type="submit">Dispense selected medicine</button></div></form></section>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const patientSelect = document.getElementById('dispense_patient_id');
        const prescriptionSelect = document.getElementById('prescription_id');
        const drugSelect = document.getElementById('drug_id');
        const quantityInput = document.getElementById('dispense_quantity');
        const allPrescriptionOptions = Array.from(prescriptionSelect.options);
        const allDrugOptions = Array.from(drugSelect.options);

        const normalizeMedicineName = (value) => {
            const normalized = String(value || '').toLowerCase().trim();
            if (!normalized) {
                return '';
            }

            return normalized
                .replace(/\b(?:tablet|tablets|capsule|capsules|syrup|suspension|oral|injectable|solution|powder|drop|drops|ml|mg|mcg|g)\b/gi, ' ')
                .replace(/[^a-z0-9]+/g, '');
        };

        const isMatchingMedicine = (prescriptionName, drugName) => {
            const prescriptionBase = normalizeMedicineName(prescriptionName);
            const drugBase = normalizeMedicineName(drugName);

            if (!prescriptionBase || !drugBase) {
                return false;
            }

            return prescriptionBase === drugBase || prescriptionBase.includes(drugBase) || drugBase.includes(prescriptionBase);
        };

        const resetDrugOptions = () => {
            allDrugOptions.forEach((option) => {
                option.hidden = false;
                option.disabled = false;
            });
            drugSelect.disabled = false;
            drugSelect.value = '';
            quantityInput.value = '';
            quantityInput.removeAttribute('max');
        };

        const filterPrescriptionOptions = () => {
            const patientId = patientSelect.value;
            let firstPrescriptionValue = '';
            allPrescriptionOptions.forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                const matches = patientId !== '' && option.dataset.patientId === patientId;
                option.hidden = !matches;
                option.disabled = !matches;
                if (matches && !firstPrescriptionValue) firstPrescriptionValue = option.value;
            });
            prescriptionSelect.value = firstPrescriptionValue;
            filterDrugOptions();
        };

        const filterDrugOptions = () => {
            const selectedPrescription = prescriptionSelect.selectedOptions[0];
            if (!selectedPrescription || !selectedPrescription.value) {
                resetDrugOptions();
                return;
            }

            const prescriptionMedication = selectedPrescription.dataset.medicationName || '';
            let firstMatchValue = '';

            allDrugOptions.forEach((option) => {
                if (option.value === '') {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const matches = isMatchingMedicine(prescriptionMedication, option.dataset.drugName || '');
                option.hidden = !matches;
                option.disabled = !matches;

                if (matches && !firstMatchValue) {
                    firstMatchValue = option.value;
                }
            });

            drugSelect.value = firstMatchValue || '';
            const remaining = selectedPrescription.dataset.quantityRemaining || '';
            quantityInput.value = remaining;
            if (remaining !== '') quantityInput.max = remaining;
        };

        patientSelect.addEventListener('change', filterPrescriptionOptions);
        prescriptionSelect.addEventListener('change', filterDrugOptions);
        filterPrescriptionOptions();
    });
</script>
<section class="dashboard-grid"><article class="panel"><div class="panel-heading"><div><h2>Receive stock</h2><p>Record supplier deliveries and expiry information.</p></div></div><form method="POST" action="{{ route('pharmacy.receive') }}" class="form-grid">@csrf<div class="field"><label>Medicine</label><select name="drug_id" required>@foreach($drugs as $drug)<option value="{{ $drug->id }}">{{ $drug->name }}</option>@endforeach</select></div><div class="field"><label>Batch number</label><input name="batch_number" placeholder="Optional for legacy stock"></div><div class="field"><label>Quantity</label><input name="quantity" type="number" min="1" required></div><div class="field"><label>Reference</label><input name="reference" placeholder="GRN or purchase order" required></div><div class="field"><label>Expiry date</label><input name="expiry_date" type="date"></div><div class="form-actions field-wide"><button class="button" type="submit">Receive stock</button></div></form></article><article class="panel"><div class="panel-heading"><div><h2>Return or damaged stock</h2><p>Keep every stock correction traceable.</p></div></div><form method="POST" action="{{ route('pharmacy.adjust') }}" class="form-grid">@csrf<div class="field"><label>Medicine</label><select name="drug_id" required>@foreach($drugs as $drug)<option value="{{ $drug->id }}">{{ $drug->name }}</option>@endforeach</select></div><div class="field"><label>Action</label><select name="movement_type"><option value="return">Return to stock</option><option value="adjustment">Damaged / remove</option></select></div><div class="field"><label>Quantity</label><input name="quantity" type="number" min="1" required></div><div class="field"><label>Reference</label><input name="reference" placeholder="Return or incident number" required></div><div class="field field-wide"><label>Notes</label><input name="notes" required></div><div class="form-actions field-wide"><button class="button" type="submit">Record adjustment</button></div></form></article></section>
@endif
<section class="panel"><div class="panel-heading"><div><h2>Medication catalogue</h2><p>Current stock levels across the pharmacy.</p></div></div>@if($drugs->isEmpty())<div class="empty-state"><strong>No medicines recorded</strong>Add medicines to begin tracking pharmacy stock.</div>@else<div class="table-wrap"><table class="data-table"><thead><tr><th>Medicine</th><th>Category</th><th>Form</th><th>Stock</th><th>Expiry</th></tr></thead><tbody>@foreach($drugs as $drug)<tr><td><strong>{{ $drug->name }}</strong></td><td>{{ $drug->category ?? 'General' }}</td><td>{{ $drug->form ?? '-' }}</td><td><span class="badge {{ $drug->stock_quantity < 20 ? 'badge-danger' : 'badge-success' }}">{{ $drug->stock_quantity }} units</span></td><td>{{ $drug->expiry_date?->format('d M Y') ?? 'Not set' }}</td></tr>@endforeach</tbody></table></div>@endif</section>
<section class="panel"><div class="panel-heading"><div><h2>Batch stock</h2><p>Dispensing follows earliest expiry first.</p></div></div>@if($batches->isEmpty())<div class="empty-state">No batch stock recorded yet.</div>@else<div class="table-wrap"><table class="data-table"><thead><tr><th>Medicine</th><th>Batch</th><th>Quantity</th><th>Expiry</th></tr></thead><tbody>@foreach($batches as $batch)<tr><td>{{ $batch->drug?->name }}</td><td><strong>{{ $batch->batch_number }}</strong></td><td>{{ $batch->quantity }} units</td><td>{{ $batch->expiry_date->format('d M Y') }}</td></tr>@endforeach</tbody></table></div>@endif</section>
@endsection
