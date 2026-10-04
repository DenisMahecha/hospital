@extends('layouts.app')
@section('title', 'Prescriptions')
@section('eyebrow', 'Clinical care')
@section('content')
<section class="page-heading"><div><h1>Prescriptions</h1><p>Review active medication orders and patient instructions.</p></div><span class="badge badge-info">Clinical orders</span></section>
<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-heading"><div><h2>Prescription register</h2><p>Recent medication orders.</p></div></div>
        @if($prescriptions->isEmpty())
            <div class="empty-state"><strong>No prescriptions recorded</strong>Medication orders will appear here after clinical review.</div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Prescription</th><th>Patient</th><th>Medication</th><th>Quantity</th><th>Directions</th><th>Status</th></tr></thead><tbody>
            @foreach($prescriptions as $prescription)
                <tr><td><strong>{{ $prescription->prescription_number }}</strong></td><td>{{ $prescription->patient?->first_name }} {{ $prescription->patient?->last_name }}</td><td>{{ $prescription->medication_name }}</td><td>{{ $prescription->quantity_prescribed ?? '-' }}</td><td>{{ $prescription->dosage }} / {{ $prescription->frequency }} / {{ $prescription->duration }}</td><td><span class="badge badge-success">{{ $prescription->status }}</span></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </article>
    <article class="panel">
        <div class="panel-heading"><div><h2>New prescription</h2><p>Record a medication order for a patient.</p></div></div>
        <form method="POST" action="{{ route('prescriptions.store') }}">
            @csrf
            <div class="field"><label for="patient_id">Patient</label><select id="patient_id" name="patient_id" required><option value="">Select patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->patient_number }} - {{ $patient->first_name }} {{ $patient->last_name }}</option>@endforeach</select></div>
            <div class="field" style="margin-top:14px"><label for="medication_name">Medication</label><input id="medication_name" name="medication_name" list="drug-suggestions" autocomplete="off" required><datalist id="drug-suggestions"></datalist></div>
            <div class="field" style="margin-top:14px"><label for="quantity_prescribed">Quantity prescribed</label><input id="quantity_prescribed" name="quantity_prescribed" type="number" min="1"></div>
            <div class="field" style="margin-top:14px"><label for="dosage">Dosage</label><input id="dosage" name="dosage" placeholder="1 tablet"></div>
            <div class="field" style="margin-top:14px"><label for="frequency">Frequency</label><input id="frequency" name="frequency" placeholder="Three times daily"></div>
            <div class="field" style="margin-top:14px"><label for="duration">Duration</label><input id="duration" name="duration" placeholder="5 days"></div>
            <div class="form-actions"><button class="button" type="submit">Record prescription</button></div>
        </form>
    </article>
</section>
<script>
    (() => {
        const input = document.getElementById('medication_name');
        const list = document.getElementById('drug-suggestions');
        if (!input || !list) return;
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const query = input.value.trim();
            if (query.length < 2) {
                list.replaceChildren();
                return;
            }
            timer = setTimeout(async () => {
                const response = await fetch('{{ route('prescriptions.drug-suggestions') }}?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const suggestions = await response.json();
                list.replaceChildren(...suggestions.map((suggestion) => {
                    const option = document.createElement('option');
                    option.value = suggestion.name;
                    option.label = suggestion.label;
                    return option;
                }));
            }, 180);
        });
    })();
</script>
@endsection