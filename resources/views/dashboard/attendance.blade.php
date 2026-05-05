@extends('layouts.app')

@section('title', 'Attendance Logs')
@section('page-title', 'Attendance Logs')

@section('content')

<div class="filters">
    <form method="GET" action="{{ route('attendance.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;width:100%">
        <div class="filter-group">
            <label>Date</label>
            <input type="date" name="date" value="{{ request('date', today()->format('Y-m-d')) }}">
        </div>
        <div class="filter-group">
            <label>Employee Code</label>
            <input type="text" name="emp_code" value="{{ request('emp_code') }}" placeholder="e.g. 1, 14...">
        </div>
        <div class="filter-group">
            <label>Punch Type</label>
            <select name="punch_state">
                <option value="">All Types</option>
                <option value="0" {{ request('punch_state') == '0' ? 'selected' : '' }}>Check In</option>
                <option value="1" {{ request('punch_state') == '1' ? 'selected' : '' }}>Check Out</option>
            </select>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="{{ route('attendance.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </div>
        <div style="margin-left:auto;display:flex;align-items:flex-end">
            <a href="{{ route('attendance.export', ['filename' => 'attendance_' . (request('date') ?: today()->format('Y-m-d'))] + request()->all()) }}" class="btn btn-ghost">⬇ Export Excel</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>📋 Attendance Records
            <span style="font-size:12px;color:var(--color-muted);font-weight:400;margin-left:8px">{{ $logs->total() }} records found</span>
        </h3>
        <span style="font-size:13px;color:var(--color-muted)">{{ request('date', today()->format('d M Y')) }}</span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Department</th>
                <th>Punch Time</th>
                <th>Type</th>
                <th>Terminal</th>
                <th>Work Code</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $index => $log)
                @php $emp = $employees->firstWhere('emp_code', $log->emp_code); @endphp
                <tr>
                    <td style="color:var(--color-muted);font-size:12px">{{ $logs->firstItem() + $index }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="width:28px;height:28px;font-size:11px">{{ strtoupper(substr($emp->first_name ?? $log->emp_code, 0, 1)) }}</div>
                            <div>
                                <div style="font-weight:500;color:var(--color-text);font-size:13px">{{ $emp->first_name ?? 'Unknown' }} {{ $emp->last_name ?? '' }}</div>
                                <div style="font-size:11px;color:var(--color-muted)">{{ $log->emp_code }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--color-muted);font-size:13px">{{ $emp->department ?? '—' }}</td>
                    <td>
                        <div style="font-weight:500;font-size:14px">{{ \Carbon\Carbon::parse($log->punch_time)->format('h:i A') }}</div>
                        <div style="font-size:11px;color:var(--color-muted)">{{ \Carbon\Carbon::parse($log->punch_time)->format('d M Y') }}</div>
                    </td>
                    <td>
                        @if($log->punch_state == '0')
                            <span class="badge badge-in">▶ Check In</span>
                        @elseif($log->punch_state == '1')
                            <span class="badge badge-out">◀ Check Out</span>
                        @else
                            <span class="badge" style="background:rgba(100,116,139,0.1);color:var(--color-muted)">— Break</span>
                        @endif
                    </td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $log->terminal_alias ?? '—' }}</td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $log->work_code ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--color-muted)">No records found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pagination --}}
    @if($logs->hasPages())
    <div class="pagination">
        @if($logs->onFirstPage())
            <span class="page-link" style="opacity:0.3">← Prev</span>
        @else
            <a href="{{ $logs->previousPageUrl() }}&{{ http_build_query(request()->except('page')) }}" class="page-link">← Prev</a>
        @endif

        @foreach($logs->getUrlRange(max(1, $logs->currentPage()-2), min($logs->lastPage(), $logs->currentPage()+2)) as $page => $url)
            <a href="{{ $url }}" class="page-link {{ $page == $logs->currentPage() ? 'active' : '' }}">{{ $page }}</a>
        @endforeach

        @if($logs->hasMorePages())
            <a href="{{ $logs->nextPageUrl() }}&{{ http_build_query(request()->except('page')) }}" class="page-link">Next →</a>
        @else
            <span class="page-link" style="opacity:0.3">Next →</span>
        @endif
    </div>
    @endif
</div>

@endsection
