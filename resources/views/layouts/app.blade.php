<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Attendance') — EasyTime Dashboard</title>
    <meta name="description" content="Automated attendance management dashboard powered by EasyTime Pro">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-container {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 20px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 { margin: 0; font-size: 18px; color: white; }
        .modal-close {
            background: none;
            border: none;
            color: var(--color-muted);
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .loader {
            border: 3px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            border-top: 3px solid var(--color-accent);
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
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
        @if(Auth::user()->role === 'admin')
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
        @else
            <a href="{{ route('employee.dashboard') }}" class="nav-item {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
                <span class="icon">📊</span> My Attendance
            </a>
        @endif

        <div class="nav-section-title" style="margin-top:12px">Account</div>
        <form action="{{ route('logout') }}" method="POST" id="logout-form" style="display:none">@csrf</form>
        <a href="#" class="nav-item" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <span class="icon">🚪</span> Sign Out
        </a>
    </nav>

    <div style="padding:16px 20px; border-top: 1px solid var(--color-border); font-size:12px; color: var(--color-muted);">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px">
            <div class="avatar" style="width:24px; height:24px; font-size:10px">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <div style="color:white; font-weight:600">{{ Auth::user()->name }}</div>
                <div style="font-size:10px">{{ ucfirst(Auth::user()->role) }}</div>
            </div>
        </div>
        <div>Device: <span style="color:var(--color-green)">● Online</span></div>
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

<!-- Modal Overlay -->
<div id="modalOverlay" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="modalTitle">Modal Title</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalBody" class="modal-body">
            <!-- Content loaded via JS -->
            <div style="display:flex; justify-content:center; padding:40px;">
                <div class="loader"></div>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal(title, content) {
        document.getElementById('modalTitle').innerText = title;
        if (content) {
            document.getElementById('modalBody').innerHTML = content;
        } else {
            document.getElementById('modalBody').innerHTML = '<div style="display:flex; justify-content:center; padding:40px;"><div class="loader"></div></div>';
        }
        document.getElementById('modalOverlay').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close on overlay click
    document.getElementById('modalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
</script>

@stack('scripts')
</body>
</html>
