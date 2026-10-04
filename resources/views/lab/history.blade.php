@extends('layouts.app')
@section('title', 'Laboratory result history')
@section('eyebrow', 'Diagnostics')
@section('content')
<section class="page-heading"><div><h1>Laboratory result history</h1><p>{{ $labRequest->request_number }} - {{ $labRequest->test_name }} - {{ $labRequest->patient?->first_name }} {{ $labRequest->patient?->last_name }}</p></div><a class="button button-quiet" href="{{ route('lab.index') }}">Back to laboratory</a></section>
<section class="panel"><div class="panel-heading"><div><h2>Result versions</h2><p>Every completed result and amendment is retained.</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Action</th><th>Result</th><th>Reference range</th><th>Changed by</th><th>Reason</th></tr></thead><tbody>@forelse($labRequest->resultHistories as $history)<tr><td>{{ $history->created_at->format('d M Y, H:i') }}</td><td><span class="badge {{ $history->action === 'amended' ? 'badge-warning' : 'badge-success' }}">{{ ucfirst($history->action) }}</span></td><td>{{ $history->result }}</td><td>{{ $history->reference_range ?? '-' }}</td><td>{{ $history->changedBy?->name ?? 'User record' }}</td><td>{{ $history->reason ?? '-' }}</td></tr>@empty<tr><td colspan="6">No result history recorded.</td></tr>@endforelse</tbody></table></div></section>
@endsection
