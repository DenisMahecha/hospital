@extends('layouts.app')
@section('title', 'New admission')
@section('eyebrow', 'Inpatient care')
@section('content')
<section class="page-heading">
    <div>
        <h1>New admission</h1>
        <p>Assign a patient to inpatient care and allocate an available bed.</p>
    </div>
    <span class="badge badge-info">Inpatient intake</span>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Admit patient</h2>
            <p>Select patient, ward, and optional bed assignment.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admissions.store') }}" class="form-grid" style="gap:0.9rem;">
        @csrf
        <div class="field">
            <label>Patient</label>
            <select name="patient_id" required>
                <option value="">Select patient</option>
                @foreach($patients as $patient)
                    <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Ward</label>
            <select name="ward_id" required>
                <option value="">Select ward</option>
                @foreach($wards as $ward)
                    <option value="{{ $ward->id }}">{{ $ward->name }} ({{ max(0, $ward->capacity - $ward->occupied_beds) }} beds available)</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Bed</label>
            <select name="bed_id">
                <option value="">Auto-assign bed</option>
                @foreach($wards as $ward)
                    @foreach($ward->beds as $bed)
                        <option value="{{ $bed->id }}">{{ $ward->name }} - {{ $bed->bed_number }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>

        <div class="field field-wide">
            <label>Notes</label>
            <textarea name="notes" rows="5" placeholder="Any admission note or observation"></textarea>
        </div>

        <div class="form-actions field-wide">
            <button class="button" type="submit">Admit patient</button>
        </div>
    </form>
</section>
@endsection
