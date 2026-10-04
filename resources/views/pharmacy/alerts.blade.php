@extends('layouts.app')
@section('title', 'Pharmacy alerts')
@section('eyebrow', 'Pharmacy')
@section('content')
<section class="page-heading"><div><h1>Medication risk review</h1><p>Review medicines that need urgent replenishment or expiry follow-up.</p></div></section>

@if($lowStockDrugs->isEmpty() && $expiringDrugs->isEmpty())
    <section class="panel">
        <div class="empty-state"><strong>No active stock alerts</strong>All medicines are within safe stock and expiry thresholds.</div>
    </section>
@else
    <section class="dashboard-grid">
        @if($lowStockDrugs->isNotEmpty())
            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Low stock alerts</h2>
                        <p>Medicines below the minimum reorder threshold.</p>
                    </div>
                </div>
                <ul>
                    @foreach($lowStockDrugs as $drug)
                        <li><strong>{{ $drug->name }}</strong>: {{ $drug->stock_quantity }} units remaining</li>
                    @endforeach
                </ul>
            </article>
        @endif

        @if($expiringDrugs->isNotEmpty())
            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Expiry alerts</h2>
                        <p>Medicines expiring within the next 90 days.</p>
                    </div>
                </div>
                <ul>
                    @foreach($expiringDrugs as $drug)
                        <li><strong>{{ $drug->name }}</strong>: expires {{ $drug->expiry_date->format('d M Y') }}</li>
                    @endforeach
                </ul>
            </article>
        @endif
    </section>
@endif

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Recommended action</h2>
            <p>Prioritise replenishment and batch review before dispensing high-risk medicines.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Current stock</th>
                    <th>Expiry</th>
                    <th>Priority</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lowStockDrugs->merge($expiringDrugs)->unique('id') as $drug)
                    <tr>
                        <td>{{ $drug->name }}</td>
                        <td>{{ $drug->stock_quantity ?? 'N/A' }} units</td>
                        <td>{{ $drug->expiry_date?->format('d M Y') ?? 'Not set' }}</td>
                        <td>
                            @if($drug->stock_quantity !== null && $drug->stock_quantity < 20)
                                <span class="badge badge-danger">Low stock</span>
                            @elseif($drug->expiry_date && $drug->expiry_date <= now()->addDays(90))
                                <span class="badge badge-warning">Expiry risk</span>
                            @else
                                <span class="badge badge-success">Monitor</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No stock risks identified.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
