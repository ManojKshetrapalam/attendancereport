<div class="drilldown-summary" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:24px;">
    <div class="stat-card blue" style="padding:16px;">
        <div style="font-size:12px; color:var(--color-muted)">Present Days</div>
        <div style="font-size:24px; font-weight:700;">{{ $presentDays }} / {{ $workingDays }}</div>
    </div>
    <div class="stat-card yellow" style="padding:16px;">
        <div style="font-size:12px; color:var(--color-muted)">Late Arrivals</div>
        <div style="font-size:24px; font-weight:700;">{{ $lateDays }}x</div>
    </div>
    <div class="stat-card green" style="padding:16px;">
        <div style="font-size:12px; color:var(--color-muted)">Avg Hours</div>
        <div style="font-size:24px; font-weight:700;">{{ $avgHours ?? '—' }}h</div>
    </div>
</div>

<div class="card" style="border-radius:12px;">
    <table class="data-table" style="font-size:13px;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Status</th>
                <th>First In</th>
                <th>Last Out</th>
                <th>Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyStats as $date => $stats)
                <tr>
                    <td style="font-weight:500;">{{ \Carbon\Carbon::parse($date)->format('d M, D') }}</td>
                    <td>
                        @if($stats['present'])
                            <span class="badge badge-present">Present</span>
                            @if($stats['late'])
                                <span class="badge badge-late">Late</span>
                            @endif
                        @else
                            <span class="badge badge-absent">Absent</span>
                        @endif
                    </td>
                    <td style="color:var(--color-muted)">{{ $stats['first_in'] ? \Carbon\Carbon::parse($stats['first_in'])->format('h:i A') : '—' }}</td>
                    <td style="color:var(--color-muted)">{{ $stats['last_out'] ? \Carbon\Carbon::parse($stats['last_out'])->format('h:i A') : '—' }}</td>
                    <td style="font-weight:600;">{{ $stats['hours'] ? $stats['hours'] . 'h' : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
