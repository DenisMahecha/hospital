@extends('layouts.app')
@section('title', 'Clinical zones')
@section('eyebrow', 'Department segmentation')
@section('content')
<section class="page-heading">
    <div>
        <h1>Clinical zones</h1>
        <p>ICU, labour & maternity, paediatric, and other dedicated care units.</p>
    </div>
    <span class="badge badge-info">Zone overview</span>
</section>

<section class="stats-grid">
    @foreach($summary as $zone => $count)
        <article class="stat-card">
            <div class="stat-label">{{ $zone }}</div>
            <div class="stat-value">{{ $count }}</div>
            <div class="stat-note">Admitted patients</div>
        </article>
    @endforeach
</section>

@foreach($zones as $zoneName => $zoneWards)
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>{{ $zoneName }}</h2>
                <p>Dedicated clinical units and occupancy status.</p>
            </div>
        </div>

        @if($zoneWards->isEmpty())
            <div class="empty-state">
                <strong>No configured units</strong>
                No wards exist for this zone yet.
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>Type</th>
                            <th>Gender</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($zoneWards as $ward)
                            <tr>
                                <td>{{ $ward->name }}</td>
                                <td>{{ ucfirst(str_replace('-', ' ', $ward->ward_type ?? 'general')) }}</td>
                                <td>{{ ucfirst($ward->gender_group ?? 'mixed') }}</td>
                                <td>{{ $ward->capacity }}</td>
                                <td>{{ $ward->occupied_beds }}</td>
                                <td>{{ $ward->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endforeach
@endsection
