@extends('layouts.app')
@section('title', 'Wards')
@section('eyebrow', 'Inpatient care')
@section('content')
<section class="page-heading">
    <div>
        <h1>Wards</h1>
        <p>Manage inpatient units, beds, and occupancy.</p>
    </div>
    <span class="badge badge-info">Inpatient services</span>
</section>

@if(auth()->user()->hasAnyRole(['super-admin', 'hospital-manager']))
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Add ward</h2>
            <p>Beds are generated from capacity.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('wards.store') }}" class="form-grid">
        @csrf
        <div class="field">
            <label>Name</label>
            <input name="name" required placeholder="Medical Ward">
        </div>
        <div class="field">
            <label>Zone</label>
            <select name="zone" required>
                <option value="General">General</option>
                <option value="ICU">ICU</option>
                <option value="Labour & Maternity">Labour & Maternity</option>
                <option value="Paediatrics">Paediatrics</option>
                <option value="Emergency">Emergency</option>
                <option value="High Dependency">High Dependency</option>
            </select>
        </div>
        <div class="field">
            <label>Ward type</label>
            <select name="ward_type" required>
                <option value="general">General</option>
                <option value="critical-care">Critical care</option>
                <option value="maternity">Maternity</option>
                <option value="paediatric">Paediatric</option>
                <option value="observation">Observation</option>
                <option value="postnatal">Postnatal</option>
                <option value="medical">Medical</option>
                <option value="surgical">Surgical</option>
            </select>
        </div>
        <div class="field">
            <label>Gender group</label>
            <select name="gender_group" required>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="women">Women</option>
                <option value="children">Children</option>
                <option value="mixed">Mixed</option>
            </select>
        </div>
        <div class="field">
            <label>Capacity</label>
            <input name="capacity" type="number" min="1" required>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="form-actions field-wide">
            <button class="button" type="submit">Create ward</button>
        </div>
    </form>
</section>
@endif

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Ward capacity</h2>
            <p>Current occupancy and bed availability.</p>
        </div>
    </div>
    <div class="stats-grid" style="margin-bottom:1rem;">
        @foreach($zoneSummary as $label => $count)
            <article class="stat-card" style="padding:0.85rem;">
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value" style="font-size:1.15rem;">{{ $count }}</div>
                <div class="stat-note">Configured units</div>
            </article>
        @endforeach
    </div>

    @if($wards->isEmpty())
        <div class="empty-state">
            <strong>No wards configured</strong>
            Create a ward to assign admitted patients.
        </div>
    @else
        <div class="ward-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem;">
            @foreach($wards as $ward)
                <article class="panel" style="margin:0; border:1px solid rgba(148,163,184,0.25); border-radius:12px; box-shadow:0 6px 18px rgba(15,23,42,0.03); padding:0.75rem;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.6rem;">
                        <div>
                            <h3 style="margin:0; font-size:0.98rem;">{{ $ward->name }}</h3>
                        </div>
                        <span class="badge {{ $ward->occupied_beds >= $ward->capacity ? 'badge-danger' : 'badge-success' }}" style="font-size:0.67rem; padding:0.25rem 0.45rem;">
                            {{ $ward->occupied_beds >= $ward->capacity ? 'Full' : $ward->status }}
                        </span>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:0.35rem; margin-bottom:0.6rem;">
                        <span class="badge badge-secondary" style="font-size:0.62rem;">{{ $ward->zone ?? 'General' }}</span>
                        <span class="badge badge-info" style="font-size:0.62rem;">{{ ucfirst(str_replace('-', ' ', $ward->ward_type ?? 'general')) }}</span>
                        <span class="badge badge-warning" style="font-size:0.62rem;">{{ ucfirst($ward->gender_group ?? 'mixed') }}</span>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:0.45rem; margin-bottom:0.75rem;">
                        <div style="padding:0.45rem 0.5rem; background:linear-gradient(135deg, #f8fafc, #eef2ff); border-radius:8px; border:1px solid rgba(148,163,184,0.15);">
                            <small style="display:block; color:#64748b; font-size:0.62rem; margin-bottom:0.12rem;">Occ.</small>
                            <strong style="font-size:0.92rem;">{{ $ward->occupied_beds }}</strong>
                        </div>
                        <div style="padding:0.45rem 0.5rem; background:linear-gradient(135deg, #f0fdf4, #ecfeff); border-radius:8px; border:1px solid rgba(148,163,184,0.15);">
                            <small style="display:block; color:#64748b; font-size:0.62rem; margin-bottom:0.12rem;">Avail.</small>
                            <strong style="font-size:0.92rem;">{{ max(0, $ward->capacity - $ward->occupied_beds) }}</strong>
                        </div>
                        <div style="padding:0.45rem 0.5rem; background:linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius:8px; border:1px solid rgba(148,163,184,0.15);">
                            <small style="display:block; color:#64748b; font-size:0.62rem; margin-bottom:0.12rem;">Cap.</small>
                            <strong style="font-size:0.92rem;">{{ $ward->capacity }}</strong>
                        </div>
                    </div>

                    @if(auth()->user()->hasAnyRole(['super-admin', 'hospital-manager']))
                        <form method="POST" action="{{ route('wards.update', $ward) }}" class="form-grid" style="margin-top:0.45rem; padding-top:0.6rem; border-top:1px solid rgba(148,163,184,0.18);">
                            @csrf
                            @method('PATCH')
                            <div class="field" style="margin-bottom:0.35rem;">
                                <label style="font-size:0.7rem;">Name</label>
                                <input name="name" value="{{ $ward->name }}" required style="font-size:0.78rem; padding:0.4rem 0.5rem;">
                            </div>
                            <div class="field" style="margin-bottom:0.35rem;">
                                <label style="font-size:0.7rem;">Zone</label>
                                <select name="zone" style="font-size:0.78rem; padding:0.4rem 0.5rem;">
                                    <option value="General" @selected(($ward->zone ?? 'General') === 'General')>General</option>
                                    <option value="ICU" @selected(($ward->zone ?? 'General') === 'ICU')>ICU</option>
                                    <option value="Labour & Maternity" @selected(($ward->zone ?? 'General') === 'Labour & Maternity')>Labour & Maternity</option>
                                    <option value="Paediatrics" @selected(($ward->zone ?? 'General') === 'Paediatrics')>Paediatrics</option>
                                    <option value="Emergency" @selected(($ward->zone ?? 'General') === 'Emergency')>Emergency</option>
                                    <option value="High Dependency" @selected(($ward->zone ?? 'General') === 'High Dependency')>High Dependency</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0.35rem;">
                                <label style="font-size:0.7rem;">Type</label>
                                <select name="ward_type" style="font-size:0.78rem; padding:0.4rem 0.5rem;">
                                    <option value="general" @selected(($ward->ward_type ?? 'general') === 'general')>General</option>
                                    <option value="critical-care" @selected(($ward->ward_type ?? 'general') === 'critical-care')>Critical care</option>
                                    <option value="maternity" @selected(($ward->ward_type ?? 'general') === 'maternity')>Maternity</option>
                                    <option value="paediatric" @selected(($ward->ward_type ?? 'general') === 'paediatric')>Paediatric</option>
                                    <option value="medical" @selected(($ward->ward_type ?? 'general') === 'medical')>Medical</option>
                                    <option value="surgical" @selected(($ward->ward_type ?? 'general') === 'surgical')>Surgical</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0.35rem;">
                                <label style="font-size:0.7rem;">Gender</label>
                                <select name="gender_group" style="font-size:0.78rem; padding:0.4rem 0.5rem;">
                                    <option value="male" @selected(($ward->gender_group ?? 'mixed') === 'male')>Male</option>
                                    <option value="female" @selected(($ward->gender_group ?? 'mixed') === 'female')>Female</option>
                                    <option value="women" @selected(($ward->gender_group ?? 'mixed') === 'women')>Women</option>
                                    <option value="children" @selected(($ward->gender_group ?? 'mixed') === 'children')>Children</option>
                                    <option value="mixed" @selected(($ward->gender_group ?? 'mixed') === 'mixed')>Mixed</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom:0.35rem;">
                                <label style="font-size:0.7rem;">Capacity</label>
                                <input name="capacity" type="number" min="1" value="{{ $ward->capacity }}" required style="font-size:0.78rem; padding:0.4rem 0.5rem;">
                            </div>
                            <div class="field" style="margin-bottom:0.35rem;">
                                <label style="font-size:0.7rem;">Status</label>
                                <select name="status" style="font-size:0.78rem; padding:0.4rem 0.5rem;">
                                    <option value="active" @selected($ward->status === 'active')>Active</option>
                                    <option value="inactive" @selected($ward->status === 'inactive')>Inactive</option>
                                </select>
                            </div>
                            <div class="form-actions field-wide" style="margin-top:0.25rem;">
                                <button class="button button-secondary" type="submit" style="padding:0.45rem 0.75rem; font-size:0.72rem;">Save</button>
                            </div>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        {{ $wards->links() }}
    @endif
</section>
@endsection

