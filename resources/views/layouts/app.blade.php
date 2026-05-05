<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Attendance') — EasyTime Dashboard</title>
    <meta name="description" content="Automated attendance management dashboard powered by EasyTime Pro">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="icon">🕐</div>
        <div>
            <h1>AttendanceIQ</h1>
            <span>EasyTime Pro Sync</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Overview</div>
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="icon">📊</span> Dashboard
        </a>
        <a href="{{ route('attendance.index') }}" class="nav-item {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
            <span class="icon">📋</span> Attendance Logs
        </a>
        <a href="{{ route('employees.index') }}" class="nav-item {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <span class="icon">👥</span> Employees
        </a>

        <div class="nav-section-title" style="margin-top:12px">Reports</div>
        <a href="{{ route('reports.daily') }}" class="nav-item {{ request()->routeIs('reports.daily') ? 'active' : '' }}">
            <span class="icon">📅</span> Daily Report
        </a>
        <a href="{{ route('reports.monthly') }}" class="nav-item {{ request()->routeIs('reports.monthly') ? 'active' : '' }}">
            <span class="icon">📆</span> Monthly Summary
        </a>

        <div class="nav-section-title" style="margin-top:12px">System</div>
        <a href="{{ route('sync.run') }}" class="nav-item" onclick="return confirm('Run sync now?')">
            <span class="icon">🔄</span> Sync Now
        </a>
    </nav>

    <div style="padding:16px 20px; border-top: 1px solid var(--color-border); font-size:12px; color: var(--color-muted);">
        <div>Device: <span style="color:var(--color-green)">● Online</span></div>
        <div style="margin-top:4px">192.168.0.233</div>
    </div>
</aside>

<!-- Main content -->
<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h2>@yield('page-title', 'Dashboard')</h2>
            <div class="date">{{ now()->format('l, d F Y') }} · {{ now()->format('H:i') }}</div>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            @yield('topbar-actions')
        </div>
    </div>

    <div class="page-content">
        @if(session('success'))
            <div class="alert alert-success" data-auto-dismiss>✓ {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error" data-auto-dismiss>✗ {{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</div>

@stack('scripts')
</body>
</html>
