@extends('layouts.app')

@section('title', 'Monthly Summary')
@section('page-title', 'Monthly Attendance Summary')

@section('topbar-actions')
    <a href="{{ route('reports.monthly.export', ['filename' => 'monthly_report_' . $month] + request()->all()) }}" download="monthly_report_{{ $month }}.xlsx" target="_blank" class="btn btn-ghost">⬇ Export Excel</a>
@endsection

@section('content')

<div class="filters">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="filter-group">
            <label>Month</label>
            <input type="month" name="month" value="{{ $month }}">
        </div>
        <div class="filter-group">
            <label>Shift Start Time</label>
            <input type="time" name="shift_start" value="{{ $shiftStart }}">
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">📊 Generate</button>
        </div>
    </form>
</div>

{{-- Summary stat cards --}}
<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card blue">
        <div class="stat-icon">📅</div>
        <div class="stat-value">{{ $workingDays }}</div>
        <div class="stat-label">Working Days</div>
        <div class="stat-sub">{{ \Carbon\Carbon::parse($month)->format('F Y') }}</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">👥</div>
        <div class="stat-value">{{ $totalEmployees }}</div>
        <div class="stat-label">Employees Tracked</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">⏰</div>
        <div class="stat-value">{{ $totalLateCount }}</div>
        <div class="stat-label">Total Late Arrivals</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">❌</div>
        <div class="stat-value">{{ $totalAbsences }}</div>
        <div class="stat-label">Total Absences</div>
    </div>
</div>

<div class="grid-2">
    {{-- Attendance trend chart --}}
    <div class="card">
        <div class="card-header"><h3>📈 Daily Attendance Trend</h3></div>
        <div class="card-body">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    {{-- Top attendees --}}
    <div class="card">
        <div class="card-header"><h3>🏆 Best Attendance</h3></div>
        <div style="padding:0 8px">
            @foreach($topAttendees as $i => $row)
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--color-border)">
                    <div style="font-size:18px;width:28px;text-align:center">{{ ['🥇','🥈','🥉','4','5'][$i] ?? ($i+1) }}</div>
                    <div class="avatar">{{ strtoupper(substr($row['name'], 0, 1)) }}</div>
                    <div style="flex:1">
                        <div style="font-size:13px;font-weight:600;color:var(--color-text)">{{ $row['name'] }}</div>
                        <div style="font-size:11px;color:var(--color-muted)">{{ $row['department'] }}</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:13px;font-weight:700;color:var(--color-green)">{{ $row['present_days'] }}/{{ $workingDays }}</div>
                        <div style="font-size:11px;color:var(--color-muted)">days</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Monthly Summary Table --}}
<div class="card">
    <div class="card-header">
        <h3>📊 Employee-wise Monthly Summary — {{ \Carbon\Carbon::parse($month)->format('F Y') }}</h3>
    </div>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Present Days</th>
                    <th>Absent Days</th>
                    <th>Late Arrivals</th>
                    <th>Avg Hours/Day</th>
                    <th>Attendance %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report as $row)
                    <tr class="row-clickable" data-emp-code="{{ $row['emp_code'] }}" data-name="{{ $row['name'] }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="width:28px;height:28px;font-size:11px">{{ strtoupper(substr($row['name'], 0, 1)) }}</div>
                                <div>
                                    <div style="font-weight:500;font-size:13px">{{ $row['name'] }}</div>
                                    <div style="font-size:11px;color:var(--color-muted)">{{ $row['emp_code'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:13px;color:var(--color-muted)">{{ $row['department'] }}</td>
                        <td>
                            <span style="font-weight:600;color:var(--color-green)">{{ $row['present_days'] }}</span>
                            <span style="color:var(--color-muted);font-size:12px"> / {{ $workingDays }}</span>
                        </td>
                        <td><span style="color: {{ $row['absent_days'] > 3 ? 'var(--color-red)' : 'var(--color-muted)' }};font-weight:{{ $row['absent_days'] > 3 ? '600' : '400' }}">{{ $row['absent_days'] }}</span></td>
                        <td>
                            @if($row['late_days'] > 0)
                                <span class="badge badge-late">{{ $row['late_days'] }}x late</span>
                            @else
                                <span style="color:var(--color-green);font-size:13px">✓ None</span>
                            @endif
                        </td>
                        <td style="font-size:13px">{{ $row['avg_hours'] ?? '—' }}h</td>
                        <td>
                            @php $pct = $workingDays > 0 ? round(($row['present_days'] / $workingDays) * 100) : 0; @endphp
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="flex:1;background:rgba(255,255,255,0.05);border-radius:4px;height:6px;min-width:60px">
                                    <div style="width:{{ $pct }}%;background:{{ $pct >= 80 ? 'var(--color-green)' : ($pct >= 60 ? 'var(--color-yellow)' : 'var(--color-red)') }};height:100%;border-radius:4px;transition:width 0.5s"></div>
                                </div>
                                <span style="font-size:12px;font-weight:600;color:{{ $pct >= 80 ? 'var(--color-green)' : ($pct >= 60 ? 'var(--color-yellow)' : 'var(--color-red)') }}">{{ $pct }}%</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendData = @json($dailyTrend);
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: Object.keys(trendData),
        datasets: [{
            label: 'Present',
            data: Object.values(trendData),
            borderColor: 'rgba(99,102,241,1)',
            backgroundColor: 'rgba(99,102,241,0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgba(99,102,241,1)',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 10 } } },
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b', font: { size: 11 } }, beginAtZero: true }
        }
    }
});

function showDrilldown(empCode, name) {
    const month = "{{ $month }}";
    const shiftStart = "{{ $shiftStart }}";
    const url = `{{ route('reports.monthly.drilldown') }}?emp_code=${empCode}&month=${month}&shift_start=${shiftStart}`;
    
    openModal(`Attendance Details — ${name}`);
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.getElementById('modalBody').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('modalBody').innerHTML = '<div class="alert alert-error">Error loading details.</div>';
            console.error('Error:', error);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.row-clickable').forEach(row => {
        row.addEventListener('click', () => {
            showDrilldown(row.dataset.empCode, row.dataset.name);
        });
    });
});
</script>

<style>
    .row-clickable { cursor: pointer; }
    .row-clickable:hover td { background: rgba(255,255,255,0.05) !important; }
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
@endpush
