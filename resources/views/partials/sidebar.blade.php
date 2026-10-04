@php($can = fn (array $roles) => auth()->user()->hasAnyRole($roles))
<div class="sidebar-scrim" data-sidebar-scrim></div>
<aside class="sidebar" data-sidebar aria-label="Hospital navigation">
    <div class="brand-lockup">
        <div class="brand-mark">MC</div>
        <div>
            <strong>MediCore</strong>
            <span>Hospital OS</span>
        </div>
    </div>

    <div class="sidebar-label">{{ __('ui.workspace') }}</div>
    <nav class="sidebar-nav" aria-label="Main navigation">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><span class="nav-symbol">◈</span> {{ __('ui.overview') }}</a>
        @if($can(['super-admin', 'hospital-manager', 'receptionist', 'doctor', 'lab-technician', 'cashier']))<a class="nav-link {{ request()->routeIs('patients.*') ? 'is-active' : '' }}" href="{{ route('patients.index') }}"><span class="nav-symbol">◎</span> {{ __('ui.patients') }}</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'receptionist', 'doctor']))<a class="nav-link {{ request()->routeIs('appointments.*') ? 'is-active' : '' }}" href="{{ route('appointments.index') }}"><span class="nav-symbol">◷</span> {{ __('ui.appointments') }}</a>@endif
        @if($can(['super-admin', 'doctor']))<a class="nav-link {{ request()->routeIs('doctor.zone') ? 'is-active' : '' }}" href="{{ route('doctor.zone') }}"><span class="nav-symbol">♣</span> Doctor zone</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'nurse']))<a class="nav-link {{ request()->routeIs('wards.*', 'admissions.*') ? 'is-active' : '' }}" href="{{ route('wards.index') }}"><span class="nav-symbol">▤</span> Inpatient care</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'nurse']))<a class="nav-link {{ request()->routeIs('zones.*') ? 'is-active' : '' }}" href="{{ route('zones.index') }}"><span class="nav-symbol">▣</span> Clinical zones</a>@endif
        @if($can(['super-admin', 'doctor', 'nurse']))<a class="nav-link {{ request()->routeIs('admissions.create') ? 'is-active' : '' }}" href="{{ route('admissions.create') }}"><span class="nav-symbol">＋</span> New admission</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'nurse']))<a class="nav-link {{ request()->routeIs('deaths.*') ? 'is-active' : '' }}" href="{{ route('deaths.index') }}"><span class="nav-symbol">✝</span> Death records</a>@endif
        @if($can(['super-admin', 'nurse']))<a class="nav-link {{ request()->routeIs('nurse.vitals.*') ? 'is-active' : '' }}" href="{{ route('nurse.vitals.index') }}"><span class="nav-symbol">♥</span> Nurse vitals</a>@endif
        @if($can(['super-admin', 'nurse']))<a class="nav-link {{ request()->routeIs('nursing-care.*') ? 'is-active' : '' }}" href="{{ route('nursing-care.index') }}"><span class="nav-symbol">✎</span> Nursing care</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'pharmacist']))<a class="nav-link {{ request()->routeIs('prescriptions.*') ? 'is-active' : '' }}" href="{{ route('prescriptions.index') }}"><span class="nav-symbol">✚</span> Prescriptions</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'lab-technician']))<a class="nav-link {{ request()->routeIs('lab.*') ? 'is-active' : '' }}" href="{{ route('lab.index') }}"><span class="nav-symbol">+</span> Laboratory</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'pharmacist']))<a class="nav-link {{ request()->routeIs('pharmacy.*') ? 'is-active' : '' }}" href="{{ route('pharmacy.index') }}"><span class="nav-symbol">▣</span> Pharmacy</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'pharmacist']))<a class="nav-link nav-link-sub {{ request()->routeIs('pharmacy.alerts') ? 'is-active' : '' }}" href="{{ route('pharmacy.alerts') }}"><span class="nav-symbol">└</span> Stock alerts</a>@endif
        @if($can(['super-admin', 'pharmacist']))<a class="nav-link nav-link-sub {{ request()->routeIs('pharmacy.catalogue') ? 'is-active' : '' }}" href="{{ route('pharmacy.catalogue') }}"><span class="nav-symbol">└</span> Medicine catalogue</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'pharmacist']))<a class="nav-link nav-link-sub {{ request()->routeIs('pharmacy.history') ? 'is-active' : '' }}" href="{{ route('pharmacy.history') }}"><span class="nav-symbol">└</span> Stock history</a>@endif
        @if($can(['super-admin', 'store-keeper']))<a class="nav-link {{ request()->routeIs('inventory.*') ? 'is-active' : '' }}" href="{{ route('inventory.index') }}"><span class="nav-symbol">□</span> Inventory</a><a class="nav-link nav-link-sub {{ request()->routeIs('inventory.alerts') ? 'is-active' : '' }}" href="{{ route('inventory.alerts') }}"><span class="nav-symbol">└</span> Low stock alerts</a><a class="nav-link nav-link-sub {{ request()->routeIs('suppliers.*') ? 'is-active' : '' }}" href="{{ route('suppliers.index') }}"><span class="nav-symbol">└</span> Suppliers</a><a class="nav-link nav-link-sub {{ request()->routeIs('purchase-orders.*') ? 'is-active' : '' }}" href="{{ route('purchase-orders.index') }}"><span class="nav-symbol">└</span> Purchase orders</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'cashier']))<a class="nav-link {{ request()->routeIs('billing.*') ? 'is-active' : '' }}" href="{{ route('billing.index') }}"><span class="nav-symbol">$</span> Billing</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'cashier']))<a class="nav-link nav-link-sub {{ request()->routeIs('billing.collections') ? 'is-active' : '' }}" href="{{ route('billing.collections') }}"><span class="nav-symbol">└</span> Outstanding collections</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'cashier']))<a class="nav-link {{ request()->routeIs('payments.*') ? 'is-active' : '' }}" href="{{ route('payments.index') }}"><span class="nav-symbol">↔</span> Payments</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'cashier']))<a class="nav-link {{ request()->routeIs('insurance.*') ? 'is-active' : '' }}" href="{{ route('insurance.index') }}"><span class="nav-symbol">▤</span> Insurance claims</a>@endif
    </nav>

    @if(auth()->user()?->hasAnyRole(['super-admin', 'hospital-manager', 'hr']))<div class="sidebar-label">People operations</div>@endif
    <nav class="sidebar-nav" aria-label="Specialist navigation">
        @if(auth()->user()?->hasRole('super-admin'))
            <a class="nav-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}"><span class="nav-symbol">◎</span> Users & roles</a>
        @endif
        @if(auth()->user()?->hasRole('super-admin'))
            <a class="nav-link {{ request()->routeIs('access-matrix.*') ? 'is-active' : '' }}" href="{{ route('access-matrix.index') }}"><span class="nav-symbol">▦</span> Access matrix</a>
        @endif
        @if(auth()->user()?->hasAnyRole(['super-admin', 'hospital-manager']))
            <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'is-active' : '' }}" href="{{ route('audit-logs.index') }}"><span class="nav-symbol">≡</span> Audit logs</a>
        @endif
        @if($can(['super-admin', 'hospital-manager', 'hr']))<a class="nav-link {{ request()->routeIs('hr.*') ? 'is-active' : '' }}" href="{{ route('hr.index') }}"><span class="nav-symbol">○</span> People & payroll</a>@endif
        @if(auth()->user()?->hasAnyRole(['super-admin', 'hospital-manager', 'hr']))<div class="sidebar-label sidebar-label-subsection">Clinical & support units</div>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor']))<a class="nav-link {{ request()->routeIs('emergency.*') ? 'is-active' : '' }}" href="{{ route('emergency.index') }}"><span class="nav-symbol">!</span> Emergency</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'radiologist']))<a class="nav-link {{ request()->routeIs('radiology.*') ? 'is-active' : '' }}" href="{{ route('radiology.index') }}"><span class="nav-symbol">◉</span> Radiology</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor']))<a class="nav-link {{ request()->routeIs('theatre.*') ? 'is-active' : '' }}" href="{{ route('theatre.index') }}"><span class="nav-symbol">△</span> Theatre</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor']))<a class="nav-link {{ request()->routeIs('rch.*') ? 'is-active' : '' }}" href="{{ route('rch.index') }}"><span class="nav-symbol">♡</span> RCH</a>@endif
        @if($can(['super-admin', 'hospital-manager', 'doctor', 'lab-technician', 'pharmacist', 'cashier', 'hr', 'store-keeper']))<a class="nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}" href="{{ route('reports.index') }}"><span class="nav-symbol">▥</span> Reports</a>@endif
        <a class="nav-link {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" href="{{ route('notifications.index') }}"><span class="nav-symbol">•</span> {{ __('ui.notifications') }}</a>
        @if(auth()->user()?->hasAnyRole(['patient', 'super-admin']))
            <a class="nav-link {{ request()->routeIs('portal.*') ? 'is-active' : '' }}" href="{{ route('portal.index') }}"><span class="nav-symbol">↗</span> {{ auth()->user()->hasRole('super-admin') ? 'Patient portal accounts' : 'Patient portal' }}</a>
        @endif
    </nav>

    <div class="sidebar-footnote">
        <span class="status-dot"></span>
        <div><strong>Systems operational</strong><small>All services reporting normally</small></div>
    </div>
</aside>
