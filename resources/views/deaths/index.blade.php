@extends('layouts.app')
@section('title', 'Death records')
@section('eyebrow', 'Mortality and case tracking')
@section('content')
<section class="page-heading">
    <div>
        <h1>Death records</h1>
        <p>Track deaths, mortality causes, and case location across the hospital.</p>
    </div>
    <span class="badge badge-danger">Mortality reporting</span>
</section>

@if(auth()->user()->hasAnyRole(['super-admin', 'doctor', 'nurse']))
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Record a death</h2>
            <p>Capture time, location, and cause of death.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('deaths.store') }}" class="form-grid">
        @csrf
        <div class="field">
            <label>Patient</label>
            <select name="patient_id" required>
                <option value="">Select patient</option>
                @foreach($patients as $patient)
                    <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->patient_number }})</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Date of death</label>
            <input type="date" name="date_of_death" required>
        </div>
        <div class="field">
            <label>Time of death</label>
            <input type="time" name="time_of_death">
        </div>
        <div class="field">
            <label>Location</label>
            <input type="text" name="location" placeholder="ICU / Labour Ward / Ward 4" required>
        </div>
        <div class="field">
            <label>Doctor name</label>
            <input type="text" name="doctor_name" placeholder="Doctor who confirmed death">
        </div>
        <div class="field field-wide">
            <label>Cause of death</label>
            <textarea name="cause" rows="3" required placeholder="Cause of death"></textarea>
        </div>
        <div class="field field-wide">
            <label>Notes</label>
            <textarea name="notes" rows="3" placeholder="Additional notes"></textarea>
        </div>
        <div class="form-actions field-wide">
            <button class="button" type="submit">Save death record</button>
        </div>
    </form>
</section>
@endif

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Mortality list</h2>
            <p>Recent death events and recorded causes.</p>
        </div>
    </div>
    @if($records->isEmpty())
        <div class="empty-state">
            <strong>No deaths recorded</strong>
            No mortality records have been entered yet.
        </div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Cause</th>
                        <th>Doctor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        <tr>
                            <td>{{ $record->patient?->first_name }} {{ $record->patient?->last_name }}</td>
                            <td>{{ $record->date_of_death->format('d M Y') }}</td>
                            <td>{{ $record->location }}</td>
                            <td>{{ Str::limit($record->cause, 90) }}</td>
                            <td>{{ $record->doctor_name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $records->links() }}
    @endif
</section>
@endsection
