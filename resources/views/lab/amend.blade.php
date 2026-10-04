@extends('layouts.app')
@section('title', 'Amend laboratory result')
@section('eyebrow', 'Diagnostics')
@section('content')
<section class="page-heading">
    <div>
        <h1>Amend laboratory result</h1>
        <p>{{ $labRequest->request_number }} - {{ $labRequest->test_name }}</p>
    </div>
    <a class="button button-quiet" href="{{ route('lab.index') }}">Back to laboratory</a>
</section>
<section class="panel form-panel">
    <div class="panel-heading"><div><h2>Result correction</h2><p>The previous result remains in the audit history.</p></div><div><span class="badge badge-warning">Amendment</span> <a class="button button-quiet" href="{{ route('lab.history', $labRequest) }}">View history</a></div></div>
    <div class="empty-state"><strong>Current result</strong><span>{{ $labRequest->result }}</span></div>
    <form method="POST" action="{{ route('lab.amend', $labRequest) }}" class="form-grid" style="margin-top:18px">
        @csrf
        <div class="field field-wide"><label for="result">Corrected result</label><textarea id="result" name="result" required placeholder="Enter the corrected laboratory result"></textarea></div>
        <div class="field"><label for="reference_range">Reference range</label><input id="reference_range" name="reference_range" value="{{ $labRequest->reference_range }}"></div>
        <div class="field"><label for="reason">Reason for amendment</label><input id="reason" name="reason" required placeholder="Explain why the result is being corrected"></div>
        <div class="form-actions field-wide"><a class="button button-quiet" href="{{ route('lab.index') }}">Cancel</a><button class="button" type="submit">Save amendment</button></div>
    </form>
</section>
@endsection
