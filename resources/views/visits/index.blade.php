@extends('layouts.app')
@section('title', 'OPD consultations')
@section('eyebrow', 'Clinical')
@section('content')
<section class="page-heading"><div><h1>OPD consultations</h1><p>Record complaints, diagnosis, clinical notes and follow-up.</p></div><span class="badge badge-info">Clinical workspace</span></section>
@if(auth()->user()->hasAnyRole(['super-admin', 'doctor']))
<section class="panel"><div class="panel-heading"><div><h2>New consultation</h2><p>Doctor records the patient encounter.</p></div></div>
<form method="POST" action="{{ route('visits.store') }}" class="form-grid">@csrf
    <div class="field field-wide"><label for="appointment_id">Assigned appointment</label><select id="appointment_id" name="appointment_id"><option value="">Select appointment or enter patient below</option>@foreach($appointments as $appointment)<option value="{{ $appointment->id }}">{{ $appointment->appointment_number }} - {{ $appointment->patient?->first_name }} {{ $appointment->patient?->last_name }} ({{ $appointment->scheduled_at->format('d M H:i') }})</option>@endforeach</select></div>
    <div class="field"><label>Patient</label><select name="patient_id" required><option value="">Select patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->patient_number }})</option>@endforeach</select></div>
    <div class="field"><label>Department</label><input name="department" required value="OPD"></div>
    <div class="field"><label>ICD-10 code</label><input name="icd10_code" placeholder="e.g. J06.9"></div>
    <div class="field field-wide"><label>Complaint</label><textarea name="complaint" required></textarea></div><div class="field field-wide"><label>Diagnosis</label><textarea name="diagnosis"></textarea></div><div class="field field-wide"><label>Clinical notes</label><textarea name="notes"></textarea></div><div class="field"><label>Referral notes</label><textarea name="referral_notes"></textarea></div><div class="field"><label>Follow-up date</label><input type="date" name="follow_up_date"></div><div class="form-actions field-wide"><button class="button" type="submit">Save consultation</button></div>
</form></section>
@endif
<section class="panel"><div class="panel-heading"><div><h2>Recent consultations</h2><p>Clinical encounters and nursing observations.</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Visit</th><th>Patient</th><th>Complaint</th><th>Diagnosis</th><th>Vitals</th></tr></thead><tbody>@forelse($visits as $visit)<tr><td>{{ $visit->visit_number }}</td><td>{{ $visit->patient?->first_name }} {{ $visit->patient?->last_name }}</td><td>{{ $visit->complaint }}</td><td>{{ $visit->diagnosis ?: 'Pending' }}</td><td>@if($visit->vitals->isNotEmpty()) Recorded @else Pending @endif</td></tr>@empty<tr><td colspan="5">No consultations recorded.</td></tr>@endforelse</tbody></table></div></section>
@if(auth()->user()->hasAnyRole(['super-admin', 'doctor']))
<section class="panel"><div class="panel-heading"><div><h2>Record vital signs</h2><p>Record observations against an existing visit.</p></div></div><form method="POST" action="{{ route('visits.vitals.store', ['visit' => $visits->first()?->id ?? 0]) }}" class="form-grid">@csrf
    <div class="field"><label>Visit</label><select name="visit_id" onchange="this.form.action='{{ url('/visits') }}/'+this.value+'/vitals'" required><option value="">Select visit</option>@foreach($visits as $visit)<option value="{{ $visit->id }}">{{ $visit->visit_number }} - {{ $visit->patient?->first_name }} {{ $visit->patient?->last_name }}</option>@endforeach</select></div><div class="field"><label>Temperature</label><input name="temperature" type="number" step="0.1"></div><div class="field"><label>Blood pressure</label><input name="blood_pressure" placeholder="120/80"></div><div class="field"><label>Weight (kg)</label><input name="weight" type="number" step="0.01"></div><div class="field"><label>Heart rate</label><input name="heart_rate" type="number"></div><div class="field"><label>Respiratory rate</label><input name="respiratory_rate" type="number"></div><div class="form-actions field-wide"><button class="button" type="submit">Save selected visit vitals</button></div>
</form></section>
@endif
@endsection
