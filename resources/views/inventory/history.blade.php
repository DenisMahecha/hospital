@extends('layouts.app')
@section('title', 'Inventory history')
@section('eyebrow', 'Supply chain')
@section('content')
<section class="page-heading"><div><h1>Inventory movement history</h1><p>Trace receipts, issues, returns, and damaged stock.</p></div><a class="button button-secondary" href="{{ route('inventory.index') }}">Inventory</a></section>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Item</th><th>Movement</th><th>Quantity</th><th>Remarks</th><th>Recorded by</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->created_at->format('d M Y H:i') }}</td><td>{{ $movement->inventoryItem?->name }}</td><td>{{ $movement->movement_type }}</td><td>{{ $movement->quantity }}</td><td>{{ $movement->remarks }}</td><td>{{ $movement->movedBy?->name ?? '-' }}</td></tr>@empty<tr><td colspan="6">No inventory movements recorded.</td></tr>@endforelse</tbody></table></div>{{ $movements->links() }}</section>
@endsection