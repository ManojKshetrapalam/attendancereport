@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('topbar-actions')
    <a href="{{ route('sync.run') }}" class="sync-btn" onclick="return confirm('Run sync from EasyTime Pro now?')">
        🔄 Sync Now
    </a>
@endsection

@section('content')

{{-- Stats Row --}}
<div class="stats-grid">
    <a href="{{ route('dashboard.index', ['view' => 'present']) }}" class="stat-card green {{ request('view') === 'present' ? 'active' : '' }}" style="text-decoration:none">
        <div class="stat-icon">✅</div>
        <div class="stat-value">{{ $todayPresent }}</div>
        <div class="stat-label">Present Today</div>
        <div class="stat-sub">Out of {{ $totalEmployees }} employees</div>
    </a>
    <a href="{{ route('dashboard.index', ['view' => 'absent']) }}" class="stat-card red {{ request('view') === 'absent' ? 'active' : '' }}" style="text-decoration:none">
        <div class="stat-icon">❌</div>
        <div class="stat-value">{{ $todayAbsent }}</div>
        <div class="stat-label">Absent Today</div>
        <div class="stat-sub">{{ $totalEmployees > 0 ? round(($todayAbsent/$totalEmployees)*100) : 0 }}% absence rate</div>
    </a>
    <a href="{{ route('dashboard.index', ['view' => 'late']) }}" class="stat-card yellow {{ request('view') === 'late' ? 'active' : '' }}" style="text-decoration:none">
        <div class="stat-icon">⏰</div>
        <div class="stat-value">{{ $todayLate }}</div>
        <div class="stat-label">Late Arrivals</div>
        <div class="stat-sub">After {{ $shiftStart }}</div>
    </a>
    <div class="stat-card blue">
        <div class="stat-icon">📈</div>
        <div class="stat-value">{{ $complianceRate }}%</div>
        <div class="stat-label">Shift Compliance</div>
        <div class="stat-sub">{{ $onTimeCount ?? 0 }} on-time today</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">⏳</div>
        <div class="stat-value">{{ $avgLateMinutes }}m</div>
        <div class="stat-label">Avg. Late Duration</div>
        <div class="stat-sub">Across {{ $todayLate }} employees</div>
    </div>
</div>

@if($drilldownTitle)
<div class="card" id="drilldown-section" style="margin-bottom:24px; border: 2px solid var(--color-accent)">
    <div class="card-header" style="background: rgba(99, 102, 241, 0.05)">
        <h3 style="color: var(--color-accent)">🔍 {{ $drilldownTitle }} ({{ $drilldownData->count() }})</h3>
        <a href="{{ route('dashboard.index') }}" style="color:var(--color-muted);text-decoration:none;font-size:12px">✕ Close Drilldown</a>
    </div>
    <div style="max-height: 400px; overflow-y: auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Status Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach($drilldownData as $emp)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar">{{ strtoupper(substr($emp->first_name ?? $emp->emp_code, 0, 1)) }}</div>
                            <div>
                                <div style="font-weight:600;color:var(--color-text)">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                <div style="font-size:11px;color:var(--color-muted)">ID: {{ $emp->emp_code }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $emp->department }}</td>
                    <td>
                        @if($emp->status_info)
                            <span class="badge" style="background:rgba(99,102,241,0.1); color:var(--color-accent)">{{ $emp->status_info }}</span>
                        @else
                            <span class="badge badge-absent">ABSENT</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="grid-2">
    {{-- Weekly trend chart --}}
    <div class="card">
        <div class="card-header">
            <h3>📈 7-Day Attendance Trend</h3>
            <span style="font-size:12px;color:var(--color-muted)">Daily present count</span>
        </div>
        <div class="card-body">
            <canvas id="weeklyChart"></canvas>
        </div>
    </div>

    {{-- Department chart --}}
    <div class="card">
        <div class="card-header">
            <h3>🏢 Staff by Department</h3>
            <span style="font-size:12px;color:var(--color-muted)">Total distribution</span>
        </div>
        <div class="card-body" style="display:flex;justify-content:center">
            <div style="max-width:300px;width:100%">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Chart + Absent List --}}
<div class="grid-3-1">
    {{-- Hourly punches chart --}}
    <div class="card">
        <div class="card-header">
            <h3>📊 Today's Hourly Punch Activity</h3>
            <span style="font-size:12px;color:var(--color-muted)">{{ today()->format('d M Y') }}</span>
        </div>
        <div class="card-body">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>

    {{-- Absent employees --}}
    <div class="card">
        <div class="card-header">
            <h3>🚫 Absent Today</h3>
        </div>
        <div style="max-height:280px;overflow-y:auto">
            @forelse($absentEmployees as $emp)
                <div style="display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--color-border)">
                    <div class="avatar">{{ strtoupper(substr($emp->first_name ?? $emp->emp_code, 0, 1)) }}</div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--color-text)">{{ $emp->first_name ?? 'Employee' }} {{ $emp->last_name }}</div>
                        <div style="font-size:12px;color:var(--color-muted)">{{ $emp->department ?? 'N/A' }}</div>
                    </div>
                </div>
            @empty
                <div style="padding:24px;text-align:center;color:var(--color-muted);font-size:14px">
                    🎉 All employees present!
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Today's Punches Table --}}
<div class="card">
    <div class="card-header">
        <h3>📋 Today's Punch Records</h3>
        <a href="{{ route('attendance.index', ['date' => today()->format('Y-m-d')]) }}" style="font-size:13px;color:var(--color-accent);text-decoration:none">View all →</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Punch Time</th>
                <th>Type</th>
                <th>Terminal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($todayPunches as $punch)
                @php
                    $emp = $employees->firstWhere('emp_code', $punch->emp_code);
                    $isLate = $punch->punch_state == '0' && \Carbon\Carbon::parse($punch->punch_time)->format('H:i') > $shiftStart;
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar">{{ strtoupper(substr($emp->first_name ?? $punch->emp_code, 0, 1)) }}</div>
                            <div>
                                <div style="font-weight:600;color:var(--color-text)">{{ $emp->first_name ?? 'Unknown' }} {{ $emp->last_name ?? '' }}</div>
                                <div style="font-size:12px;color:var(--color-muted)">ID: {{ $punch->emp_code }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--color-muted);font-size:13px">{{ $emp->department ?? '—' }}</td>
                    <td style="font-weight:500">{{ \Carbon\Carbon::parse($punch->punch_time)->format('h:i A') }}</td>
                    <td>
                        @if($punch->punch_state == '0')
                            <span class="badge badge-in">▶ IN</span>
                            @if($isLate)<span class="badge badge-late" style="margin-left:4px">LATE</span>@endif
                        @elseif($punch->punch_state == '1')
                            <span class="badge badge-out">◀ OUT</span>
                        @else
                            <span class="badge" style="background:rgba(100,116,139,0.1);color:var(--color-muted)">— BREAK</span>
                        @endif
                    </td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $punch->terminal_alias ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:var(--color-muted);padding:40px">No punch records for today yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection

@push('scripts')
<script>
// Common chart options
const commonScales = {
    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 } } },
    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 } }, beginAtZero: true }
};

// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
const hourlyData = @json($hourlyData);
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: Object.keys(hourlyData).map(h => h + ':00'),
        datasets: [{
            label: 'Punches',
            data: Object.values(hourlyData),
            backgroundColor: 'rgba(99, 102, 241, 0.5)',
            borderColor: 'rgba(99, 102, 241, 1)',
            borderWidth: 1,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: commonScales
    }
});

// Weekly Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
const weeklyData = @json($weeklyTrend);
new Chart(weeklyCtx, {
    type: 'line',
    data: {
        labels: Object.keys(weeklyData),
        datasets: [{
            label: 'Present Count',
            data: Object.values(weeklyData),
            borderColor: '#22C55E',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#22C55E',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: commonScales
    }
});

// Department Chart
const deptCtx = document.getElementById('deptChart').getContext('2d');
const deptData = @json($deptDistribution);
new Chart(deptCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(deptData),
        datasets: [{
            data: Object.values(deptData),
            backgroundColor: [
                '#6366F1', '#22C55E', '#F59E0B', '#EF4444', '#EC4899', '#8B5CF6', '#14B8A6'
            ],
            borderWidth: 0,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { 
                position: 'bottom',
                labels: { color: '#94a3b8', font: { size: 11 }, boxWidth: 12, padding: 15 }
            }
        },
        cutout: '70%'
    }
});
</script>
@endpush
