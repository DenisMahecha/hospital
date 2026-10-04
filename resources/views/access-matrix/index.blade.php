@extends('layouts.app')
@section('title', 'Role access matrix')
@section('eyebrow', 'Administration')
@section('content')
<section class="page-heading">
    <div><h1>Role access matrix</h1><p>Approved access boundaries for hospital departments.</p></div>
    <button class="button button-secondary" type="button" onclick="window.print()">Print / Save PDF</button>
</section>

<section class="panel">
    <div class="panel-heading"><div><h2>Watumiaji na ruhusa</h2><p>✅ Full access, ✏️ editing, 👁 view only, ❌ no access.</p></div></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Module</th>@foreach($roles as $role)<th>{{ $role }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach($matrix as $row)
                    <tr>
                        <th>{{ $row['module'] }}</th>
                        @foreach($row['access'] as $access)
                            <td>@if($access === 'Full') ✅ @elseif($access === 'Edit') ✏️ @elseif($access === 'View') 👁 @elseif($access === 'Own') Zake @else ❌ @endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><div><h2>Department role linkage</h2><p>Review each department separately and check which roles are linked to it.</p></div></div>

    @foreach($departmentLinkage as $department)
        <div style="margin-bottom: 24px; padding: 16px; border: 1px solid rgba(148,163,184,0.35); border-radius: 12px; background: rgba(15,23,42,0.02);">
            <h3 style="margin: 0 0 6px; font-size: 1.1rem;">{{ $department['label'] }}</h3>
            <p style="margin: 0 0 14px; color: #475569;">{{ $department['summary'] }}</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            @foreach($department['roles'] as $role)
                                <th>{{ $role }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($department['modules'] as $module)
                            <tr>
                                <th>{{ $module['module'] }}</th>
                                @foreach($department['roles'] as $role)
                                    @php $value = $module['access'][$role] ?? 'None'; @endphp
                                    <td>
                                        @if($value === 'Full') ✅
                                        @elseif($value === 'Edit') ✏️
                                        @elseif($value === 'View') 👁
                                        @elseif($value === 'Own') Zake
                                        @else ❌
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</section>
@endsection