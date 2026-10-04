@extends('layouts.app')
@section('title', 'Imaging result')
@section('eyebrow', 'Patient portal')
@section('content')
<section class="page-heading"><div><h1>{{ $radiologyRequest->modality }}</h1><p>{{ $radiologyRequest->request_number }} - Report reviewed and recorded securely.</p></div><a class="button button-quiet" href="{{ route('portal.index') }}">Back to portal</a></section>
<section class="dashboard-grid"><article class="panel"><div class="panel-heading"><div><h2>Report</h2><p>Completed {{ $radiologyRequest->reported_at?->format('d M Y, H:i') ?? '-' }}</p></div><span class="badge badge-success">Completed</span></div><p class="result-value">{{ $radiologyRequest->report }}</p>@if($radiologyRequest->image_path)<p><a class="button button-secondary" href="{{ route('radiology.attachment', $radiologyRequest) }}" target="_blank">View protected image attachment</a></p>@endif</article><article class="panel"><div class="panel-heading"><div><h2>Request details</h2></div></div><p><strong>Patient:</strong> {{ $radiologyRequest->patient?->first_name }} {{ $radiologyRequest->patient?->last_name }}</p><p><strong>Modality:</strong> {{ $radiologyRequest->modality }}</p><p><strong>Reported by:</strong> {{ $radiologyRequest->reportedBy?->name ?? 'Medical officer' }}</p></article></section>
@endsection
