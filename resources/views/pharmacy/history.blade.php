@extends('layouts.app')
@section('title', 'Pharmacy history')
@section('eyebrow', 'Pharmacy')
@section('content')
<section class="page-heading"><div><h1>Pharmacy stock history</h1><p>Every receipt, dispense, return, and adjustment is traceable.</p></div></section>
<form method="GET" class="panel form-grid"><div class="field"><label for="history-search">Search stock history</label><input id="history-search" name="q" value="{{ $search }}" placeholder="Medicine, movement, or reference"></div><div class="form-actions"><button class="button button-secondary" type="submit">Search</button></div></form>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Medicine</th><th>Batch</th><th>Movement</th><th>Quantity</th><th>Reference</th><th>Notes</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->created_at->format('d M Y H:i') }}</td><td>{{ $movement->drug?->name }}</td><td>{{ $movement->batch?->batch_number ?? '-' }}</td><td>{{ $movement->movement_type }}</td><td>{{ $movement->quantity }}</td><td>{{ $movement->reference ?? '-' }}</td><td>{{ $movement->notes ?? '-' }}</td></tr>@empty<tr><td colspan="7">No stock movements recorded.</td></tr>@endforelse</tbody></table></div>{{ $movements->links() }}</section>
@endsection