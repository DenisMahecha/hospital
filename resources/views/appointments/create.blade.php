@extends('layouts.app')
@section('title', 'Book appointment')
@section('eyebrow', 'Patient access')
@section('content')
<section class="page-heading"><div><div class="crumb-context"><a href="{{ route('appointments.index') }}">Patient access</a> <span>/</span> Book appointment</div><h1>Book appointment</h1><p>Create a future visit for an active patient.</p></div><a class="button button-quiet" href="{{ route('appointments.index') }}">Back to appointment book</a></section>
<section class="panel form-panel">
    <div class="panel-heading"><div><h2>Find patient and schedule visit</h2><p>Search by name or patient number before selecting a patient.</p></div><span class="badge badge-info">Scheduling</span></div>
    <form method="GET" action="{{ route('appointments.create') }}" class="form-grid"><div class="field"><label for="patient-search">Find patient</label><input id="patient-search" name="patient_search" value="{{ $patientSearch }}" list="patient-suggestions" autocomplete="off" placeholder="Name or patient number"><datalist id="patient-suggestions"></datalist></div><div class="form-actions"><button class="button button-secondary" type="submit">Find patient</button></div></form>
    @if($patientSearch !== '' && $patients->isEmpty())<div class="empty-state"><strong>No active patient found</strong>Try another name or patient number.</div>@endif
    <form method="POST" action="{{ route('appointments.store') }}" class="form-grid" style="margin-top:18px">@csrf
        <div class="field field-wide"><label for="patient_id">Patient</label><select id="patient_id" name="patient_id" required><option value="">Select active patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->patient_number }} - {{ $patient->first_name }} {{ $patient->last_name }}</option>@endforeach</select></div>
        <div class="field"><label for="scheduled_at">Date and time</label><input id="scheduled_at" name="scheduled_at" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" required></div>
        <div class="field"><label for="department">Department</label><select id="department" name="department" required><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department }}">{{ $department }}</option>@endforeach</select></div>
        <div class="field"><label for="doctor_id">Doctor</label><select id="doctor_id" name="doctor_id"><option value="">Assign doctor later</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}">{{ $doctor->name }}</option>@endforeach</select></div>
        <div class="field field-wide"><label for="reason">Reason for visit</label><textarea id="reason" name="reason"></textarea></div>
        <div class="form-actions field-wide"><a class="button button-quiet" href="{{ route('appointments.index') }}">Cancel</a><button class="button" type="submit">Schedule appointment</button></div>
    </form>
</section>
<script>
    (() => {
        const input = document.getElementById('patient-search');
        const list = document.getElementById('patient-suggestions');
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
                const response = await fetch('{{ route('appointments.patient-suggestions') }}?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' } });
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
