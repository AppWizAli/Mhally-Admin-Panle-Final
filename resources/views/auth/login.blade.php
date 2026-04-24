<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="login-body">
<div class="login-shell">
    <section class="login-showcase">
        <span class="pill accent">Buyer + Supplier Control</span>
        <h1>Run the Muhalli marketplace from one responsive admin panel.</h1>
        <p>The backend is designed around the actual buyer and supplier app flow: onboarding, accounts, catalog, supplier listings, inventory, orders, chats, earnings, and public app settings.</p>
        <div class="showcase-grid">
            <article class="showcase-card">
                <strong>Frontend + Backend</strong>
                <p>Laravel, Blade, CSS, JavaScript, and MySQL using the same admin experience as the original panel.</p>
            </article>
            <article class="showcase-card">
                <strong>App-Ready APIs</strong>
                <p>JSON endpoints for both apps are available in the same project for Android integration.</p>
            </article>
            <article class="showcase-card">
                <strong>Responsive Layout</strong>
                <p>The admin shell adapts for desktop, tablet, and mobile operations.</p>
            </article>
        </div>
    </section>

    <section class="login-card">
        <div class="login-card__header">
            <span class="pill">Admin Access</span>
            <h2>Welcome back</h2>
            <p>Use the seeded admin credentials after running the Laravel migration.</p>
        </div>

        @if(session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert danger">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login.submit') }}" class="stack-form">
            @csrf

            <label>
                <span>Email address</span>
                <input type="email" name="email" value="{{ old('email', 'admin@muhalli.test') }}" required>
            </label>

            <label>
                <span>Password</span>
                <input type="password" name="password" value="{{ old('password', 'password') }}" required>
            </label>

            <button class="primary-button full-width" type="submit">Sign in</button>
        </form>

        <div class="login-footnote">
            <strong>Seed credentials</strong>
            <p>admin@muhalli.test / password</p>
            <small>If login fails, run the Laravel migration and database seed first.</small>
        </div>
    </section>
</div>
</body>
</html>
