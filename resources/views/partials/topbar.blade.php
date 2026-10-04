<header class="topbar">
    <button class="mobile-menu" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">☰</button>
    <div class="crumbs"><span>{{ __('ui.hospital_operations') }}</span><b>/</b><strong>@yield('eyebrow', __('ui.overview'))</strong></div>
    @php($unreadNotifications = auth()->user()?->notifications()->whereNull('read_at')->count() ?? 0)
    <div class="topbar-actions">
        <a class="topbar-icon" href="{{ route('dashboard') }}" aria-label="{{ __('ui.dashboard') }}">⌂</a>
        <a class="topbar-icon topbar-notification" href="{{ route('notifications.index') }}" aria-label="Notifications">
            ●
            @if($unreadNotifications > 0)
                <span class="notification-badge">{{ $unreadNotifications }}</span>
            @endif
        </a>
        <div class="user-chip">
            <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="user-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->getRoleNames()->first() ?? 'Staff' }}</span></div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="button button-quiet" type="submit">{{ __('ui.sign_out') }}</button>
        </form>
    </div>
</header>
