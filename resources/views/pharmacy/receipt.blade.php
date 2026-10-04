@extends('layouts.app')
@section('title', 'Dispensing receipt')
@section('eyebrow', 'Pharmacy')
@section('content')
<section class="page-heading"><div><h1>Dispensing receipt</h1><p>Prescription {{ $prescription->prescription_number }}</p></div><button class="button button-secondary" type="button" onclick="window.print()">Print receipt</button></section>
<section class="panel"><p><strong>Patient:</strong> {{ $prescription->patient?->first_name }} {{ $prescription->patient?->last_name }}</p><p><strong>Dispensed by:</strong> {{ $prescription->dispensedBy?->name ?? '-' }}</p><div class="table-wrap"><table class="data-table"><thead><tr><th>Medicine</th><th>Batch</th><th>Quantity</th><th>Reference</th></tr></thead><tbody>@foreach($prescription->stockMovements as $movement)<tr><td>{{ $movement->drug?->name }}</td><td>{{ $movement->batch?->batch_number ?? '-' }}</td><td>{{ $movement->quantity }}</td><td>{{ $movement->reference ?? '-' }}</td></tr>@endforeach</tbody></table></div><p><strong>Instructions:</strong> {{ $prescription->dosage }} / {{ $prescription->frequency }} / {{ $prescription->duration }}</p></section>
@endsection