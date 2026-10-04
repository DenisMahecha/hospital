@extends('layouts.app')
@section('title', 'Patient timeline')
@section('eyebrow', 'Clinical view')
@section('content')
<section class="page-heading">
    <div>
        <div class="crumb-context"><a href="{{ route('patients.index') }}">Patients</a> <span>/</span> Clinical timeline</div>
        <h1>{{ $patient->first_name }} {{ $patient->last_name }}</h1>
        <p>Full patient journey across appointments, consultations, laboratory, prescriptions, and billing.</p>
    </div>
    <a class="button button-secondary" href="{{ route('patients.index') }}">Back to registry</a>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Clinical timeline</h2>
            <p>{{ $patient->patient_number }} • {{ $patient->phone ?? 'No phone on record' }}</p>
        </div>
    </div>

    @if($timeline->isEmpty())
        <div class="empty-state">
            <strong>No clinical activity recorded.</strong>
            This patient has no appointments, visits, lab requests, or billing events yet.
        </div>
    @else
        <div class="timeline-list">
            @foreach($timeline as $entry)
                <article class="timeline-item">
                    <div class="timeline-time">{{ \Carbon\Carbon::parse($entry['time'])->format('d M Y, H:i') }}</div>
                    <div class="timeline-body">
                        <div class="timeline-title">{{ $entry['title'] }}</div>
                        <div class="timeline-subtitle">{{ $entry['subtitle'] }}</div>
                        <div class="timeline-content">{{ $entry['content'] }}</div>
                        <div class="timeline-meta">{{ $entry['meta'] }}</div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
