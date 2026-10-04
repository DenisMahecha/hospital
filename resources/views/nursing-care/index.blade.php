@extends('layouts.app')
@section('title', 'Nursing Care')
@section('eyebrow', 'Inpatient care')
@section('content')
<section class="page-heading">
    <div>
        <h1>Nursing care</h1>
        <p>Record patient progress, observation, care notes, and clinical handover.</p>
    </div>
    <span class="badge badge-info">IPD notes</span>
</section>

<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-heading">
            <div>
                <h2>Record note</h2>
                <p>For active admissions only.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('nursing-care.notes.store') }}" class="form-grid" style="gap:0.9rem;">
            @csrf
            <div class="field">
                <label style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600;">Admission</label>
                <select name="admission_id" required style="width:100%; min-height:38px; padding:0.55rem 0.7rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.9rem;">
                    <option value="">Select patient</option>
                    @foreach($admissions as $admission)
                        <option value="{{ $admission->id }}">{{ $admission->patient?->first_name }} {{ $admission->patient?->last_name }} - {{ $admission->ward?->name ?? 'Unassigned' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600;">Note type</label>
                <select name="note_type" required style="width:100%; min-height:38px; padding:0.55rem 0.7rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.9rem;">
                    <option value="progress">Progress</option>
                    <option value="observation">Observation</option>
                    <option value="care">Care</option>
                    <option value="medication">Medication</option>
                    <option value="handover">Handover</option>
                </select>
            </div>

            <div class="field field-wide">
                <label style="display:block; margin-bottom:0.35rem; font-size:0.8rem; font-weight:600;">Note</label>
                <textarea name="note" rows="6" required placeholder="Record assessment, condition updates, care given, and follow-up actions..." style="width:100%; min-height:120px; padding:0.7rem 0.8rem; border-radius:8px; border:1px solid #cbd5e1; font-size:0.9rem; resize:vertical;"></textarea>
            </div>

            <div class="form-actions field-wide">
                <button class="button" type="submit" style="min-height:38px;">Save note</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <div class="panel-heading">
            <div>
                <h2>Current admissions</h2>
                <p>Patients active in inpatient care.</p>
            </div>
        </div>

        @if($admissions->isEmpty())
            <div class="empty-state">
                <strong>No active admissions</strong>
                There are no admitted patients to document today.
            </div>
        @else
            <div class="quick-grid">
                @foreach($admissions as $admission)
                    <div class="quick-link" style="padding:0.8rem; border:1px solid rgba(148,163,184,0.2); border-radius:10px; background:#fff;">
                        <div style="font-weight:600; margin-bottom:0.2rem;">{{ $admission->patient?->first_name }} {{ $admission->patient?->last_name }}</div>
                        <small style="display:block; color:#64748b; margin-bottom:0.35rem;">{{ $admission->ward?->name ?? 'Unassigned' }}</small>
                        <small style="color:#475569;">{{ $admission->nursingNotes->count() }} notes</small>
                    </div>
                @endforeach
            </div>
        @endif
    </article>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Recent nursing care</h2>
            <p>Latest documented notes across inpatient care.</p>
        </div>
    </div>

    @if($recentNotes->isEmpty())
        <div class="empty-state">
            <strong>No notes recorded yet</strong>
            Nursing care entries will appear here.
        </div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Note</th>
                        <th>Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentNotes as $note)
                        <tr>
                            <td>{{ $note->recorded_at?->format('d M H:i') }}</td>
                            <td>{{ $note->admission?->patient?->first_name }} {{ $note->admission?->patient?->last_name }}</td>
                            <td>{{ $note->note_type }}</td>
                            <td>{{ Str::limit($note->note, 120) }}</td>
                            <td>{{ $note->recordedBy?->name ?? 'System' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
