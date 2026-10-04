<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password | MediCore</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-intro">
            <div class="brand-lockup"><div class="brand-mark">MC</div><div><strong>MediCore</strong><span>Hospital OS</span></div></div>
            <h1>Keep your access secure.</h1>
            <p>We will send a one-time reset link to your hospital email address.</p>
        </section>
        <section class="login-form">
            <h2>Forgot password?</h2>
            <p class="lead">Enter your account email and check your inbox.</p>
            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="field"><label for="email">Work email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus></div>
                <div class="form-actions"><button class="button" type="submit">Send reset link</button></div>
            </form>
            <p style="font-size:12px;margin-top:20px"><a href="{{ route('login') }}" style="color:var(--teal)">Back to sign in</a></p>
        </section>
    </main>
</body>
</html>
