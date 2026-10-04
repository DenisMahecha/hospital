@extends('layouts.app')
@section('title', 'Outstanding collections')
@section('eyebrow', 'Revenue cycle')
@section('content')
<section class="page-heading"><div><h1>Outstanding collections</h1><p>Review unpaid and partially paid patient balances that need cashier follow-up.</p></div><span class="badge badge-warning">TZS {{ number_format($totalOutstanding) }}</span></section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Collections queue</h2>
            <p>Prioritise the highest outstanding balances for follow-up and payment recovery.</p>
        </div>
    </div>

    @if($invoices->isEmpty())
        <div class="empty-state"><strong>No outstanding balances</strong>All invoices are fully settled.</div>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Invoice</th>
                        <th>Service</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td><strong>{{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</strong></td>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->service_description ?: 'Service billing' }}</td>
                            <td>TZS {{ number_format($invoice->total_amount) }}</td>
                            <td>TZS {{ number_format((float) ($invoice->payments_sum_amount ?? 0)) }}</td>
                            <td><strong>TZS {{ number_format((float) $invoice->outstanding_balance) }}</strong></td>
                            <td><span class="badge {{ $invoice->status === 'paid' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($invoice->status) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
