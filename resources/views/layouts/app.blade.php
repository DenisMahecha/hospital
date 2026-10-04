<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hospital Command Center') | {{ config('app.name', 'MediCore HMS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell">
        @include('partials.sidebar')
        <div class="app-main-wrap">
            @include('partials.topbar')
            <main id="main-content" class="app-content">
                @include('partials.flash')
                @yield('content')
            </main>
            <footer class="app-footer">MediCore Hospital Management System <span>•</span> {{ now()->format('Y') }}</footer>
        </div>
    </div>
</body>
</html>
