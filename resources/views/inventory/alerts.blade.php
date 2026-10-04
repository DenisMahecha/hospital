@extends('layouts.app')
@section('title', 'Low stock alerts')
@section('eyebrow', 'Inventory')
@section('content')
<section class="page-heading"><div><h1>Low stock alerts</h1><p>Review items below reorder levels and create purchase orders before stock runs out.</p></div></section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Reorder priority</h2>
            <p>Items that are at or below their reorder threshold.</p>
        </div>
    </div>

    @if($items->isEmpty())
        <div class="empty-state"><strong>No low stock alerts</strong>All tracked inventory items are within safe working levels.</div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Code</th>
                        <th>Current stock</th>
                        <th>Reorder level</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td><strong>{{ $item->name }}</strong></td>
                            <td>{{ $item->item_code }}</td>
                            <td>{{ $item->current_stock }}</td>
                            <td>{{ $item->reorder_level }}</td>
                            <td>{{ $item->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
