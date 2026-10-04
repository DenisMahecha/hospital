@extends('layouts.app')
@section('title', 'Radiology')
@section('eyebrow', 'Diagnostics')
@section('content')
<section class="page-heading">
    <div>
        <h1>Radiology</h1>
        <p>Request imaging and record radiologist reports.</p>
    </div>
    <span class="badge badge-info">Imaging unit</span>
</section>

@if(auth()->user()->hasAnyRole(['super-admin', 'doctor']))
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Request imaging</h2>
            <p>Doctor sends an imaging request.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('radiology.store') }}" class="form-grid">
        @csrf
        <div class="field">
            <label for="radiology-patient">Patient</label>
            <select id="radiology-patient" name="patient_id" required>
                <option value="">Select patient</option>
                @foreach($patients as $patient)
                    <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="radiology-modality">Modality</label>
            <select id="radiology-modality" name="modality" required>
                <option value="X-Ray">X-Ray</option>
                <option value="Ultrasound">Ultrasound</option>
                <option value="CT">CT</option>
                <option value="MRI">MRI</option>
            </select>
        </div>
        <div class="form-actions field-wide">
            <button class="button" type="submit">Send imaging request</button>
        </div>
    </form>
</section>
@endif

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Imaging requests and reports</h2>
            <p>Latest requests across modalities.</p>
        </div>
    </div>

    @if($requests->isEmpty())
        <div class="empty-state"><strong>No imaging requests</strong></div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Modality</th>
                        <th>Patient</th>
                        <th>Status/report</th>
                        @if(auth()->user()->hasAnyRole(['super-admin', 'radiologist']))
                            <th>Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $radiologyRequest)
                        <tr>
                            <td><strong>{{ $radiologyRequest->request_number }}</strong></td>
                            <td>{{ $radiologyRequest->modality }}</td>
                            <td>{{ $radiologyRequest->patient?->first_name ?? 'Patient record' }}</td>
                            <td>
                                {{ $radiologyRequest->report ?: $radiologyRequest->status }}
                                @if($radiologyRequest->image_path)
                                    @if(strtolower(pathinfo($radiologyRequest->image_path, PATHINFO_EXTENSION)) === 'dcm')
                                        <div class="dicom-viewer" data-dicom-viewer data-image-url="{{ route('radiology.attachment', $radiologyRequest) }}">
                                            <div class="dicom-canvas" data-dicom-canvas></div>
                                            <div class="dicom-controls">
                                                <button type="button" data-dicom-reset>Reset</button>
                                                <button type="button" data-dicom-invert>Invert</button>
                                                <span data-dicom-status>Loading DICOM...</span>
                                            </div>
                                        </div>
                                    @else
                                        <a href="{{ route('radiology.attachment', $radiologyRequest) }}" target="_blank">View protected attachment</a>
                                    @endif
                                @endif
                            </td>
                            @if(auth()->user()->hasAnyRole(['super-admin', 'radiologist']))
                                <td>
                                    @if($radiologyRequest->status !== 'completed')
                                        <form method="POST" action="{{ route('radiology.report', $radiologyRequest) }}" enctype="multipart/form-data">
                                            @csrf
                                            <textarea name="report" required placeholder="Radiology report"></textarea>
                                            <input type="file" name="image" accept=".jpg,.jpeg,.png,.pdf,.dcm">
                                            <button class="button button-secondary" type="submit">Complete report</button>
                                        </form>
                                    @else
                                        <span class="badge badge-success">Completed</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@vite('resources/js/dicom-viewer.js')
@endsection
