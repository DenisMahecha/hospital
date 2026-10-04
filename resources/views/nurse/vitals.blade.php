@extends('layouts.app')
@section('title', 'Nurse vitals')
@section('eyebrow', 'Inpatient care')
@section('content')
<section class="page-heading">
    <div>
        <h1>Nurse vitals</h1>
        <p>Record temperature, pulse, oxygen, BP, and patient condition for active admissions.</p>
    </div>
    <span class="badge badge-info">Clinical observations</span>
</section>

<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-heading">
            <div>
                <h2>Record vitals</h2>
                <p>Quick patient assessment for active admissions.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('nurse.vitals.store') }}" class="form-grid" style="gap:0.9rem;">
            @csrf
            <div class="field">
                <label>Patient</label>
                <select name="admission_id" required>
                    <option value="">Select patient</option>
                    @foreach($admissions as $admission)
                        <option value="{{ $admission->id }}">{{ $admission->patient?->first_name }} {{ $admission->patient?->last_name }} - {{ $admission->ward?->name ?? 'Unassigned' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Temperature</label>
                <input type="number" step="0.1" min="25" max="45" name="temperature" placeholder="36.8">
            </div>

            <div class="field">
                <label>Blood pressure</label>
                <input type="text" name="blood_pressure" placeholder="120/80">
            </div>

            <div class="field">
                <label>Pulse</label>
                <input type="number" min="20" max="250" name="pulse" placeholder="72">
            </div>

            <div class="field">
                <label>Respiratory rate</label>
                <input type="number" min="5" max="80" name="respiratory_rate" placeholder="18">
            </div>

            <div class="field">
                <label>Oxygen saturation</label>
                <input type="number" step="0.1" min="0" max="100" name="oxygen_saturation" placeholder="98">
            </div>

            <div class="field">
                <label>Weight</label>
                <input type="number" step="0.1" min="0" max="500" name="weight" placeholder="65.5">
            </div>

            <div class="field">
                <label>Consciousness</label>
                <select name="consciousness">
                    <option value="">Select</option>
                    <option value="alert">Alert</option>
                    <option value="voice">Responds to voice</option>
                    <option value="pain">Responds to pain</option>
                    <option value="unresponsive">Unresponsive</option>
                </select>
            </div>

            <div class="form-actions field-wide">
                <button class="button" type="submit">Save vitals</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <div class="panel-heading">
            <div>
                <h2>Recent vitals</h2>
                <p>Latest recorded observations.</p>
            </div>
        </div>

        @if($recentVitals->isEmpty())
            <div class="empty-state">
                <strong>No vitals recorded</strong>
                Recent observation entries will appear here.
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Vitals</th>
                            <th>Recorded by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentVitals as $vital)
                            <tr>
                                <td>{{ $vital->recorded_at?->format('d M H:i') }}</td>
                                <td>{{ $vital->admission?->patient?->first_name }} {{ $vital->admission?->patient?->last_name }}</td>
                                <td>
                                    @if($vital->temperature) T {{ $vital->temperature }}°C<br>@endif
                                    @if($vital->blood_pressure) BP {{ $vital->blood_pressure }}<br>@endif
                                    @if($vital->pulse) Pulse {{ $vital->pulse }}<br>@endif
                                    @if($vital->oxygen_saturation) O2 {{ $vital->oxygen_saturation }}%<br>@endif
                                    @if($vital->consciousness) {{ ucfirst($vital->consciousness) }}@endif
                                </td>
                                <td>{{ $vital->recordedBy?->name ?? 'System' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
</section>
@endsection
