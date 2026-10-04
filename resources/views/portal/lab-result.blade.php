@extends('layouts.app')
@section('title', 'Laboratory result')
@section('eyebrow', 'Patient portal')
@section('content')
<section class="page-heading"><div><h1>{{ $labRequest->test_name }}</h1><p>{{ $labRequest->request_number }} - Result viewed and recorded securely.</p></div><a class="button button-quiet" href="{{ route('portal.index') }}">Back to portal</a></section>
<section class="dashboard-grid"><article class="panel"><div class="panel-heading"><div><h2>Result</h2><p>Completed {{ $labRequest->completed_at?->format('d M Y, H:i') ?? '-' }}</p></div><span class="badge badge-success">Completed</span></div><p class="result-value">{{ $labRequest->result }}</p><p><strong>Reference range:</strong> {{ $labRequest->reference_range ?? 'Not provided' }}</p></article><article class="panel"><div class="panel-heading"><div><h2>Request details</h2></div></div><p><strong>Patient:</strong> {{ $labRequest->patient?->first_name }} {{ $labRequest->patient?->last_name }}</p><p><strong>Sample status:</strong> {{ ucfirst($labRequest->status) }}</p><p><strong>History entries:</strong> {{ $labRequest->resultHistories->count() }}</p></article></section>
@endsection
