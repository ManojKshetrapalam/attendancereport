@extends('layouts.app')

@section('title', 'My Attendance')
@section('page-title', 'My Attendance')

@section('topbar-actions')
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-ghost">🚪 Sign Out</button>
    </form>
@endsection

@section('content')

<div class="filters">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="filter-group">
            <label>Month</label>
            <input type="month" name="month" value="{{ $month }}">
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">📊 View History</button>
        </div>
    </form>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card blue">
        <div class="stat-icon">📅</div>
        <div class="stat-value">{{ $presentDays }} / {{ $workingDays }}</div>
        <div class="stat-label">Days Present</div>
        <div class="stat-sub">{{ \Carbon\Carbon::parse($month)->format('F Y') }}</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">⏰</div>
        <div class="stat-value">{{ $lateDays }}</div>
        <div class="stat-label">Late Arrivals</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value">{{ $avgHours ?? '—' }}h</div>
        <div class="stat-label">Avg Hours/Day</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">📈</div>
        <div class="stat-value">
            @php $pct = $workingDays > 0 ? round(($presentDays / $workingDays) * 100) : 0; @endphp
            {{ $pct }}%
        </div>
        <div class="stat-label">Attendance Score</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>📋 Daily Attendance Logs — {{ \Carbon\Carbon::parse($month)->format('F Y') }}</h3>
    </div>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>First In</th>
                    <th>Last Out</th>
                    <th>Total Hours</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $startDate = \Carbon\Carbon::parse($month . '-01');
                    $endDate = $startDate->copy()->endOfMonth();
                    if ($endDate->isFuture()) {
                        $endDate = now();
                    }
                @endphp

                @for($d = $endDate->copy(); $d->gte($startDate); $d->subDay())
                    @php
                        $dateStr = $d->format('Y-m-d');
                        $dayLogs = $groupedLogs->get($dateStr, collect());
                        $present = $dayLogs->isNotEmpty();
                        $isSunday = $d->dayOfWeek === \Carbon\Carbon::SUNDAY;
                    @endphp

                    @if(!$isSunday || $present)
                        <tr>
                            <td style="font-weight:500;">
                                {{ $d->format('d M, Y') }}
                                <div style="font-size:11px; color:var(--color-muted)">{{ $d->format('l') }}</div>
                            </td>
                            <td>
                                @if($present)
                                    <span class="badge badge-present">Present</span>
                                    @php
                                        $firstIn = $dayLogs->where('punch_state', '0')->sortBy('punch_time')->first();
                                        $late = $firstIn && \Carbon\Carbon::parse($firstIn->punch_time)->format('H:i') > $shiftStart;
                                    @endphp
                                    @if($late)
                                        <span class="badge badge-late">Late</span>
                                    @endif
                                @else
                                    <span class="badge badge-absent">Absent</span>
                                @endif
                            </td>
                            <td style="color:var(--color-muted)">
                                {{ $present && $dayLogs->where('punch_state', '0')->first() ? \Carbon\Carbon::parse($dayLogs->where('punch_state', '0')->sortBy('punch_time')->first()->punch_time)->format('h:i A') : '—' }}
                            </td>
                            <td style="color:var(--color-muted)">
                                {{ $present && $dayLogs->where('punch_state', '1')->last() ? \Carbon\Carbon::parse($dayLogs->where('punch_state', '1')->sortBy('punch_time')->last()->punch_time)->format('h:i A') : '—' }}
                            </td>
                            <td>
                                @php
                                    $h = null;
                                    $fi = $dayLogs->where('punch_state', '0')->sortBy('punch_time')->first();
                                    $lo = $dayLogs->where('punch_state', '1')->sortBy('punch_time')->last();
                                    if ($fi && $lo) {
                                        $h = round(\Carbon\Carbon::parse($fi->punch_time)->diffInMinutes(\Carbon\Carbon::parse($lo->punch_time)) / 60, 1);
                                    }
                                @endphp
                                <span style="font-weight:600; color:var(--color-text)">{{ $h ? $h . 'h' : '—' }}</span>
                            </td>
                        </tr>
                    @endif
                @endfor
            </tbody>
        </table>
    </div>
</div>

@endsection
