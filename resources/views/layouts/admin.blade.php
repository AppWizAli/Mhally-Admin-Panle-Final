@php
    use App\Support\AdminUi;

    $activeModule = request()->routeIs('admin.dashboard') || request()->routeIs('admin.dashboard.alias')
        ? 'dashboard'
        : (string) request()->route('module', 'dashboard');
    $pageAction = View::yieldContent('page_action', 'list');
    $meta = AdminUi::pageMeta($activeModule, $pageAction);
    $adminUser = session('admin_user', []);
    $adminName = $adminUser['full_name'] ?? 'Admin User';
    $adminRole = $adminUser['role'] ?? 'Super Admin';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meta['title'] }} | {{ config('app.name') }}</title>
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
                    <strong>Muhalli Market</strong>
                    <small>Admin Panel</small>
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
                <button class="logout-link" type="submit" style="border:0;background:transparent;padding:0">Log Out</button>
            </form>
        </div>
    </aside>

    <div class="content-shell">
        <header class="topbar">
            <button class="icon-button mobile-only" type="button" data-sidebar-toggle>{!! AdminUi::iconSvg('menu') !!}</button>
            <div>
                <p class="eyebrow">{{ config('app.name') }}</p>
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
            <div class="alert danger">{{ in_array($pageAction, ['create', 'edit'], true) ? 'Please review the highlighted fields below and try again.' : $errors->first() }}</div>
        @endif

        <main class="page-grid">
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
