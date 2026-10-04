<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | MediCore Hospital OS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-intro">
            <div class="brand-lockup"><div class="brand-mark">MC</div><div><strong>MediCore</strong><span>Hospital OS</span></div></div>
            <h1>Care moves better when teams see clearly.</h1>
            <p>One calm workspace for patients, clinicians, diagnostics, pharmacy, finance, and hospital operations.</p>
        </section>
        <section class="login-form">
            <h2>Welcome back</h2>
            <p class="lead">Sign in to continue to your hospital workspace.</p>
            @if ($errors->any())<div class="alert alert-danger"><span>!</span><div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div></div>@endif
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="field"><label for="email">Work email</label><input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@hospital.org" required autofocus></div>
                <div class="field" style="margin-top:16px"><label for="password">Password</label><input id="password" name="password" type="password" placeholder="Enter your password" required></div>
                <label style="display:flex;align-items:center;gap:8px;margin-top:16px;color:var(--muted);font-size:12px"><input name="remember" type="checkbox" value="1"> Remember this device</label>
                <p style="font-size:12px;margin-top:16px"><a href="{{ route('password.request') }}" style="color:var(--teal)">Forgot your password?</a></p>
                <div class="form-actions"><button class="button" type="submit">Sign in to MediCore</button></div>
            </form>
        </section>
    </main>
</body>
</html>
