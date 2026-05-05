@extends('layouts.app')

@section('title', 'Daily Report')
@section('page-title', 'Daily Attendance Report')

@section('topbar-actions')
    <a href="{{ route('reports.daily.export') }}?{{ http_build_query(request()->all()) }}" class="btn btn-ghost">⬇ Export Excel</a>
@endsection

@section('content')

<div class="filters">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="filter-group">
            <label>Report Date</label>
            <input type="date" name="date" value="{{ $date->format('Y-m-d') }}">
        </div>
        <div class="filter-group">
            <label>Shift Start Time</label>
            <input type="time" name="shift_start" value="{{ $shiftStart }}">
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">📊 Generate Report</button>
        </div>
    </form>
</div>

{{-- Summary Cards --}}
<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-value">{{ $summary['present'] }}</div>
        <div class="stat-label">Present</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">❌</div>
        <div class="stat-value">{{ $summary['absent'] }}</div>
        <div class="stat-label">Absent</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">⏰</div>
        <div class="stat-value">{{ $summary['late'] }}</div>
        <div class="stat-label">Late (after {{ $shiftStart }})</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">📈</div>
        <div class="stat-value">{{ $summary['total'] > 0 ? round(($summary['present'] / $summary['total']) * 100) : 0 }}%</div>
        <div class="stat-label">Attendance Rate</div>
    </div>
</div>

{{-- Detailed Report Table --}}
<div class="card">
    <div class="card-header">
        <h3>📅 Report for {{ $date->format('d F Y') }}</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Department</th>
                <th>First In</th>
                <th>Last Out</th>
                <th>Hours Worked</th>
                <th>Status</th>
                <th>Punches</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $i => $row)
                <tr>
                    <td style="color:var(--color-muted);font-size:12px">{{ $i + 1 }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="width:28px;height:28px;font-size:11px">{{ strtoupper(substr($row['name'], 0, 1)) }}</div>
                            <div>
                                <div style="font-weight:500;color:var(--color-text);font-size:13px">{{ $row['name'] }}</div>
                                <div style="font-size:11px;color:var(--color-muted)">{{ $row['emp_code'] }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $row['department'] }}</td>
                    <td style="font-weight:500;font-size:13px">{{ $row['first_in'] ? $row['first_in']->format('h:i A') : '—' }}</td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $row['last_out'] ? $row['last_out']->format('h:i A') : '—' }}</td>
                    <td style="font-size:13px">
                        @if($row['hours_worked'] !== null)
                            <span style="font-weight:600;color:{{ $row['hours_worked'] >= 8 ? 'var(--color-green)' : 'var(--color-yellow)' }}">
                                {{ $row['hours_worked'] }}h
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if(!$row['present'])
                            <span class="badge badge-absent">Absent</span>
                        @elseif($row['late'])
                            <span class="badge badge-late">⏰ Late</span>
                        @else
                            <span class="badge badge-present">✓ On Time</span>
                        @endif
                    </td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $row['punch_count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
