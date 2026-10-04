@extends('layouts.app')
@section('title', 'Triage queue')
@section('eyebrow', 'Front desk')
@section('content')
<section class="page-heading">
    <div>
        <div class="crumb-context"><a href="{{ route('appointments.index') }}">Appointments</a> <span>/</span> Triage queue</div>
        <h1>Triage queue</h1>
        <p>Patients waiting and checked in for today’s hospital access.</p>
    </div>
    <a class="button" href="{{ route('appointments.index') }}">Back to bookings</a>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <div class="stat-label">Waiting</div>
        <div class="stat-value">{{ $waitingCount }}</div>
        <div class="stat-note">Not yet checked in</div>
    </article>
    <article class="stat-card">
        <div class="stat-label">Checked in</div>
        <div class="stat-value">{{ $checkedInCount }}</div>
        <div class="stat-note">Front desk active queue</div>
    </article>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Today’s queue</h2>
            <p>Live front desk triage board.</p>
        </div>
    </div>

    @if($appointments->isEmpty())
        <div class="empty-state">
            <strong>No patients in today’s queue.</strong>
            Bookings will appear here as patients schedule for today.
        </div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->scheduled_at->format('H:i') }}</td>
                            <td>{{ $appointment->patient?->first_name }} {{ $appointment->patient?->last_name }}</td>
                            <td>{{ $appointment->department }}</td>
                            <td>
                                <span class="badge {{ $appointment->status === 'checked_in' ? 'badge-success' : 'badge-info' }}">
                                    {{ $appointment->status === 'checked_in' ? 'Checked in' : 'Waiting' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $appointment->status === 'checked_in' ? 'scheduled' : 'checked_in' }}">
                                    <button class="button button-secondary" type="submit">
                                        {{ $appointment->status === 'checked_in' ? 'Mark waiting' : 'Check in' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
