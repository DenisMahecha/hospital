<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCore Hospital OS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-intro"><div class="brand-lockup"><div class="brand-mark">MC</div><div><strong>MediCore</strong><span>Hospital OS</span></div></div><h1>One connected view of care.</h1><p>Bring clinical, operational, and patient services into one clear hospital workspace.</p></section>
        <section class="login-form"><span class="badge badge-success">Systems operational</span><h2 style="margin-top:20px">Welcome to MediCore</h2><p class="lead">Access is reserved for authorised hospital staff and patient portal accounts.</p><div class="form-actions"><a class="button" href="{{ route('login') }}">Sign in to continue</a></div><p style="color:var(--muted);font-size:11px;line-height:1.6;margin-top:24px">Need access? Contact your hospital administrator to provision an account.</p></section>
    </main>
</body>
</html>
