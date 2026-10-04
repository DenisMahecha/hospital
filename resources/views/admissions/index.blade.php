@extends('layouts.app')
@section('title', 'Admissions')
@section('eyebrow', 'Inpatient care')
@section('content')
<section class="page-heading"><div><h1>Admissions</h1><p>Track active patients and ward activity.</p></div><span class="badge badge-warning">Overview</span></section>

<section class="stats-grid" style="margin-bottom:1.2rem;">
    <div class="stat-card">
        <div class="stat-label">Active</div>
        <div class="stat-value">{{ $summary['active'] }}</div>
        <div class="stat-note">Current</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Today</div>
        <div class="stat-value">{{ $summary['today'] }}</div>
        <div class="stat-note">New</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Beds</div>
        <div class="stat-value">{{ $summary['available_beds'] }}</div>
        <div class="stat-note">Open</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Notes</div>
        <div class="stat-value">{{ $summary['notes_today'] }}</div>
        <div class="stat-note">Logged</div>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><div><h2>Admission register</h2><p>Current list</p></div></div>
    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end; margin-bottom:1rem;">
        <form method="GET" action="{{ route('admissions.index') }}" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end; width:100%;">
            <div class="field" style="min-width:220px; flex:1 1 220px;">
                <label>Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Patient or admission number" style="min-height:38px; padding:0.55rem 0.7rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.9rem;">
            </div>
            <div class="field" style="min-width:160px; flex:1 1 160px;">
                <label>Ward</label>
                <select name="ward_id" style="min-height:38px; padding:0.55rem 0.7rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.9rem;">
                    <option value="">All wards</option>
                    @foreach($wards as $ward)
                        <option value="{{ $ward->id }}" @selected(request('ward_id') == $ward->id)>{{ $ward->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="min-width:150px; flex:1 1 150px;">
                <label>Status</label>
                <select name="status" style="min-height:38px; padding:0.55rem 0.7rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.9rem;">
                    <option value="">All</option>
                    <option value="admitted" @selected(request('status') === 'admitted')>Admitted</option>
                    <option value="discharged" @selected(request('status') === 'discharged')>Discharged</option>
                </select>
            </div>
            <div class="form-actions" style="margin:0;">
                <button class="button" type="submit" style="min-height:38px;">Filter</button>
            </div>
        </form>
    </div>
    @if($admissions->isEmpty())
        <div class="empty-state"><strong>No admissions</strong>New admissions will be listed here.</div>
    @else
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Admission</th><th>Patient</th><th>Ward</th><th>Date</th><th>Status</th><th>Nursing care</th></tr></thead><tbody>
        @foreach($admissions as $admission)
            <tr>
                <td><strong>{{ $admission->admission_number }}</strong></td>
                <td>
                    <div style="display:flex; flex-direction:column; gap:0.3rem;">
                        <span>{{ $admission->patient?->first_name }} {{ $admission->patient?->last_name }}</span>
                        @if($admission->status === 'admitted')
                            <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                                @php
                                    $priority = $admission->vitals->isNotEmpty() && $admission->vitals->last()->oxygen_saturation !== null && $admission->vitals->last()->oxygen_saturation < 90 ? 'Critical' : 'Stable';
                                @endphp
                                <span class="badge {{ $priority === 'Critical' ? 'badge-danger' : 'badge-success' }}" style="padding:0.2rem 0.45rem; font-size:0.62rem;">{{ $priority }}</span>
                                @if($admission->medicationAdministrations->isNotEmpty())
                                    <span class="badge badge-warning" style="padding:0.2rem 0.45rem; font-size:0.62rem;">Due meds</span>
                                @endif
                                @if($admission->wardRounds->isNotEmpty())
                                    <span class="badge badge-info" style="padding:0.2rem 0.45rem; font-size:0.62rem;">Round</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </td>
                <td>{{ $admission->ward?->name ?? 'Unassigned' }}</td>
                <td>{{ $admission->admission_date?->format('d M Y H:i') ?? '-' }}</td>
                <td>
                    <div style="display:flex; flex-direction:column; gap:0.6rem; min-width:180px;">
                        <span class="badge {{ $admission->status === 'admitted' ? 'badge-info' : 'badge-success' }}" style="display:inline-flex; width:max-content;">{{ $admission->status }}</span>
                        @if($admission->status === 'admitted' && auth()->user()->hasAnyRole(['super-admin', 'doctor', 'nurse']))
                            <form method="POST" action="{{ route('admissions.discharge', $admission) }}" style="display:flex; flex-direction:column; gap:0.45rem; padding:0.55rem; border:1px solid rgba(148,163,184,0.2); border-radius:8px; background:#f8fafc;">
                                @csrf
                                <label style="font-size:0.68rem; font-weight:700; color:#334155;">Discharge</label>
                                <textarea name="discharge_summary" required placeholder="Summary" rows="2" style="width:100%; min-width:170px; min-height:58px; padding:0.5rem 0.6rem; border-radius:6px; border:1px solid #cbd5e1; font-size:0.76rem;"></textarea>
                                <input name="discharge_condition" required placeholder="Condition" style="width:100%; min-width:170px; min-height:30px; padding:0.45rem 0.5rem; border-radius:6px; border:1px solid #cbd5e1; font-size:0.76rem;">
                                <textarea name="discharge_medications" placeholder="Medications" rows="2" style="width:100%; min-width:170px; min-height:58px; padding:0.5rem 0.6rem; border-radius:6px; border:1px solid #cbd5e1; font-size:0.76rem;"></textarea>
                                <input type="date" name="follow_up_date" style="width:100%; min-width:170px; min-height:30px; padding:0.45rem 0.5rem; border-radius:6px; border:1px solid #cbd5e1; font-size:0.76rem;">
                                <button class="button button-secondary" type="submit" style="width:max-content; min-height:30px; padding:0.4rem 0.7rem; font-size:0.7rem;">Discharge</button>
                            </form>
                        @endif
                    </div>
                </td>
                <td>
                    @if($admission->nursingNotes->isNotEmpty())<ul>@foreach($admission->nursingNotes->take(3) as $note)<li><strong>{{ $note->note_type }}</strong>: {{ $note->note }}</li>@endforeach</ul>@endif
                    @if($admission->vitals->isNotEmpty())<small>Latest vitals: T {{ $admission->vitals->last()->temperature ?? '-' }}, BP {{ $admission->vitals->last()->blood_pressure ?? '-' }}, Pulse {{ $admission->vitals->last()->pulse ?? '-' }}, O2 {{ $admission->vitals->last()->oxygen_saturation ?? '-' }}</small>@endif
                    @if($admission->medicationAdministrations->isNotEmpty())<small>Medication: {{ $admission->medicationAdministrations->last()->medication_name }} ({{ $admission->medicationAdministrations->last()->status }})</small>@endif
                    @if($admission->wardRounds->isNotEmpty())<small>Latest round: {{ $admission->wardRounds->last()->note }}</small>@endif
                    @if($admission->transfers->isNotEmpty())<small>Last transfer: {{ $admission->transfers->last()->fromWard?->name ?? 'Unassigned' }} -> {{ $admission->transfers->last()->toWard?->name }}</small>@endif
                    @if($admission->status === 'admitted' && auth()->user()->hasAnyRole(['super-admin', 'nurse']))<form method="POST" action="{{ route('admissions.nursing-note', $admission) }}">@csrf<select name="note_type"><option value="progress">Progress</option><option value="observation">Observation</option><option value="care">Care</option><option value="medication">Medication</option><option value="handover">Handover</option></select><textarea name="note" required placeholder="Nursing note"></textarea><button class="button button-secondary" type="submit">Save note</button></form><form method="POST" action="{{ route('admissions.vitals', $admission) }}">@csrf<input name="temperature" type="number" step="0.1" placeholder="Temperature"><input name="blood_pressure" placeholder="BP 120/80"><input name="pulse" type="number" placeholder="Pulse"><input name="respiratory_rate" type="number" placeholder="Respiratory rate"><input name="oxygen_saturation" type="number" step="0.1" placeholder="O2 saturation"><select name="consciousness"><option value="">Consciousness</option><option value="alert">Alert</option><option value="voice">Responds to voice</option><option value="pain">Responds to pain</option><option value="unresponsive">Unresponsive</option></select><button class="button button-secondary" type="submit">Save IPD vitals</button></form><form method="POST" action="{{ route('admissions.medications', $admission) }}">@csrf<input name="medication_name" required placeholder="Medication"><input name="dose" required placeholder="Dose"><select name="route" required><option value="oral">Oral</option><option value="iv">IV</option><option value="im">IM</option><option value="sc">SC</option><option value="topical">Topical</option><option value="other">Other</option></select><select name="status"><option value="given">Given</option><option value="refused">Refused</option><option value="missed">Missed</option></select><textarea name="notes" placeholder="Medication notes"></textarea><button class="button button-secondary" type="submit">Update medication chart</button></form><form method="POST" action="{{ route('admissions.ward-round', $admission) }}">@csrf<textarea name="note" required placeholder="Ward round note"></textarea><textarea name="plan" placeholder="Care plan"></textarea><button class="button button-secondary" type="submit">Record ward round</button></form><form method="POST" action="{{ route('admissions.transfer', $admission) }}">@csrf<select name="to_ward_id" required><option value="">Transfer to ward</option>@foreach($wards as $ward)<option value="{{ $ward->id }}">{{ $ward->name }}</option>@endforeach</select><textarea name="reason" required placeholder="Transfer reason"></textarea><button class="button button-secondary" type="submit">Transfer patient</button></form>@endif
                </td>
            </tr>
        @endforeach
        </tbody></table></div>{{ $admissions->links() }}
    @endif
</section>
@endsection
