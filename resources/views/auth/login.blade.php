<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('panel.login.page_title') }} | {{ __('panel.common.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/lama-sans" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="login-body">
<div class="login-shell">
    <section class="login-showcase">
        <span class="pill accent">{{ __('panel.brand.panel') }}</span>
        <h1>{{ __('panel.brand.name') }}</h1>
        <p>{{ __('panel.login.subtitle') }}</p>
    </section>

    <section class="login-card">
        <div class="login-card__header">
            <span class="pill">{{ __('panel.login.eyebrow') }}</span>
            <h2>{{ __('panel.login.title') }}</h2>
            <p>{{ __('panel.login.subtitle') }}</p>
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
                <span>{{ __('panel.login.email') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <label>
                <span>{{ __('panel.login.password') }}</span>
                <input type="password" name="password" required>
            </label>

            <button class="primary-button full-width" type="submit">{{ __('panel.login.submit') }}</button>
        </form>
    </section>
</div>
<div class="page-loader" data-loading-overlay hidden aria-live="polite" aria-busy="true">
    <div class="page-loader__card">
        <div class="page-loader__spinner" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <strong>{{ __('panel.login.loading_title') }}</strong>
        <p data-loading-text>{{ __('panel.login.loading_body') }}</p>
    </div>
</div>
<script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
