@extends('layouts.app')
@section('title', 'Audit logs')
@section('eyebrow', 'Security')
@section('content')
<section class="page-heading"><div><h1>Audit logs</h1><p>Read-only trace of activity across the hospital system.</p></div><span class="badge badge-danger">Admin only</span></section><section class="panel"><div class="panel-heading"><div><h2>Recent activity</h2><p>Audit records cannot be deleted from the application.</p></div></div>@if($logs->isEmpty())<div class="empty-state"><strong>No audit activity</strong>System events will appear here.</div>@else<div class="table-wrap"><table class="data-table"><thead><tr><th>Time</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr></thead><tbody>@foreach($logs as $log)<tr><td>{{ $log->created_at->format('d M Y, H:i') }}</td><td><span class="badge badge-info">{{ $log->action }}</span></td><td>{{ $log->module_name }}</td><td>{{ $log->description ?? '-' }}</td><td>{{ $log->ip_address ?? '-' }}</td></tr>@endforeach</tbody></table></div>@endif</section>
@endsection
