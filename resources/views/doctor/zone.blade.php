@extends('layouts.app')
@section('title', 'Doctor zone')
@section('eyebrow', 'Clinical workspace')
@section('content')
<section class="page-heading">
    <div>
        <h1>Doctor zone</h1>
        <p>Track consultations, patient status, vitals, and urgent clinical tasks.</p>
    </div>
    <span class="badge badge-info">Doctor workspace</span>
</section>

<div style="display:grid; grid-template-columns:minmax(0,1.7fr) minmax(260px,310px); gap:1.2rem; align-items:start;">
    <article class="panel" style="padding:1rem 1.1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <div style="font-size:0.95rem; font-weight:700; color:#0f172a;">Consultation</div>
            <span class="badge badge-info" style="font-size:0.62rem;">Active</span>
        </div>

        <form method="POST" action="{{ route('visits.store') }}" class="form-grid" style="gap:0.8rem; align-items:end;">
            @csrf
            <div class="field field-wide" style="grid-column:1 / -1;">
                <label>Assigned appointment</label>
                <select name="appointment_id" required>
                    <option value="">Select assigned appointment</option>
                    @foreach($appointments as $appointment)
                        <option value="{{ $appointment->id }}">{{ $appointment->appointment_number }} - {{ $appointment->patient?->first_name }} {{ $appointment->patient?->last_name }} ({{ $appointment->scheduled_at->format('d M H:i') }})</option>
                    @endforeach
                </select>
                @if($appointments->isEmpty())<small>No confirmed or checked-in appointments assigned to you.</small>@endif
            </div>
            <div class="field field-wide" style="grid-column:1 / -1;">
                <label>Patient</label>
                <select name="patient_id" required {{ $appointments->isEmpty() ? 'disabled' : '' }}>
                    <option value="">Select patient</option>
                    @foreach($patients as $patient)
                        <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->patient_number }})</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Department</label>
                <input name="department" required value="OPD">
            </div>

            <div class="field">
                <label>ICD-10 code</label>
                <input name="icd10_code" placeholder="J06.9">
            </div>

            <div class="field field-wide">
                <label>Complaint</label>
                <textarea name="complaint" required rows="2" placeholder="Presenting complaint"></textarea>
            </div>

            <div class="field field-wide">
                <label>Diagnosis</label>
                <textarea name="diagnosis" rows="2" placeholder="Assessment and diagnosis"></textarea>
            </div>

            <div class="field field-wide">
                <label>Clinical notes</label>
                <textarea name="notes" rows="3" placeholder="Clinical assessment notes"></textarea>
            </div>

            <div class="field">
                <label>Referral notes</label>
                <textarea name="referral_notes" rows="2" placeholder="Referral"></textarea>
            </div>

            <div class="field">
                <label>Follow-up date</label>
                <input type="date" name="follow_up_date">
            </div>

            <div class="form-actions field-wide" style="margin-top:0.25rem;">
                <button class="button" type="submit">Save consultation</button>
            </div>
        </form>
    </article>

    <aside style="display:flex; flex-direction:column; gap:0.85rem;">
        <div class="panel" style="padding:0.8rem;">
            <div style="font-size:0.75rem; color:#64748b; font-weight:600; letter-spacing:0.04em; text-transform:uppercase;">Clinical actions</div>
            <div style="display:flex; flex-direction:column; gap:0.6rem; margin-top:0.8rem;">
                <a href="{{ route('visits.index') }}" class="button button-quiet" style="text-align:center;">Consultations</a>
                <a href="{{ route('lab.index') }}" class="button button-quiet" style="text-align:center;">Laboratory</a>
                <a href="{{ route('radiology.index') }}" class="button button-quiet" style="text-align:center;">Radiology</a>
                <a href="{{ route('admissions.index') }}" class="button button-quiet" style="text-align:center;">Inpatient care</a>
            </div>
        </div>

        <div class="panel" style="padding:0.9rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <span style="font-size:0.75rem; color:#64748b; font-weight:600; letter-spacing:0.04em; text-transform:uppercase;">Latest vitals</span>
                <span class="badge badge-success" style="font-size:0.62rem;">Updated</span>
            </div>
            <div style="font-size:0.8rem; color:#475569; line-height:1.7;">
                @if($recentVitals->isEmpty())
                    No recent observations.
                @else
                    @php($latest = $recentVitals->first())
                    <div>Patient: {{ $latest->visit?->patient?->first_name }} {{ $latest->visit?->patient?->last_name }}</div>
                    <div>Temp: {{ $latest->temperature ?? '-' }}°C</div>
                    <div>BP: {{ $latest->blood_pressure ?? '-' }}</div>
                    <div>HR: {{ $latest->heart_rate ?? '-' }}</div>
                @endif
            </div>
        </div>
    </aside>
</div>

<section class="panel" style="margin-top:1.25rem;">
    <div class="panel-heading">
        <div>
            <h2>Recent consultations</h2>
            <p>Latest doctor encounters and patient notes.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Visit</th>
                    <th>Patient</th>
                    <th>Complaint</th>
                    <th>Diagnosis</th>
                    <th>Vitals</th>
                </tr>
            </thead>
            <tbody>
                @forelse($visits as $visit)
                    <tr>
                        <td>{{ $visit->visit_number }}</td>
                        <td>{{ $visit->patient?->first_name }} {{ $visit->patient?->last_name }}</td>
                        <td>{{ $visit->complaint }}</td>
                        <td>{{ $visit->diagnosis ?: 'Pending' }}</td>
                        <td>
                            @if($visit->vitals->isNotEmpty())
                                @php($last = $visit->vitals->last())
                                {{ $last->temperature ? 'T '.$last->temperature : '' }}
                                {{ $last->blood_pressure ? 'BP '.$last->blood_pressure : '' }}
                                {{ $last->heart_rate ? 'HR '.$last->heart_rate : '' }}
                            @else
                                Pending
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No consultations recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
