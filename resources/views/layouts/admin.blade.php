@php
    use App\Support\AdminUi;

    $activeModule = request()->routeIs('admin.dashboard') || request()->routeIs('admin.dashboard.alias')
        ? 'dashboard'
        : (string) request()->route('module', 'dashboard');
    $pageAction = View::yieldContent('page_action', 'list');
    $meta = AdminUi::pageMeta($activeModule, $pageAction);
    $adminUser = session('admin_user', []);
    $adminName = $adminUser['full_name'] ?? __('panel.common.admin_user');
    $adminRole = $adminUser['role'] ?? __('panel.common.super_admin');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ AdminUi::isRtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meta['title'] }} | {{ __('panel.common.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/lama-sans" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="shell" data-sidebar-shell>
    <aside class="sidebar" id="sidebar">
        <div class="brand-panel">
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <span class="brand-mark">M</span>
                <span>
                    <strong>{{ __('panel.brand.name') }}</strong>
                    <small>{{ __('panel.brand.panel') }}</small>
                </span>
            </a>
        </div>
        <nav class="nav-list">
            @foreach(AdminUi::navItems() as $key => $item)
                <a class="nav-item {{ $key === $activeModule ? 'is-active' : '' }}" href="{{ $item['route'] }}">
                    <span class="nav-icon">{!! AdminUi::iconSvg($item['icon']) !!}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="logout-link" type="submit" style="border:0;background:transparent;padding:0">{{ __('panel.common.logout') }}</button>
            </form>
        </div>
    </aside>

    <div class="content-shell">
        <header class="topbar">
            <button class="icon-button mobile-only" type="button" data-sidebar-toggle>{!! AdminUi::iconSvg('menu') !!}</button>
            <div>
                <p class="eyebrow">{{ __('panel.common.app_name') }}</p>
                <h1>@yield('title', $meta['title'])</h1>
                <p class="subtle">{{ $meta['subtitle'] }}</p>
            </div>
            <div class="topbar-meta">
                <div class="user-chip">
                    <div class="avatar">{{ strtoupper(substr($adminName, 0, 1)) }}</div>
                    <div>
                        <strong>{{ $adminName }}</strong>
                        <small>{{ $adminRole }}</small>
                    </div>
                </div>
            </div>
        </header>

        @if(session('status'))
            <div class="alert success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert danger">{{ in_array($pageAction, ['create', 'edit'], true) ? __('panel.common.status_review') : $errors->first() }}</div>
        @endif

        <main class="page-grid">
            @yield('content')
        </main>
    </div>
</div>
<div class="page-loader" data-loading-overlay hidden aria-live="polite" aria-busy="true">
    <div class="page-loader__card">
        <div class="page-loader__spinner" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <strong>{{ __('panel.common.loading_title') }}</strong>
        <p data-loading-text>{{ __('panel.common.loading_body') }}</p>
    </div>
</div>
<script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
