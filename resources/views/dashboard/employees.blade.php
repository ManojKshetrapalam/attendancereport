@extends('layouts.app')

@section('title', 'Employees')
@section('page-title', 'Employees')

@section('content')

<div class="card">
    <div class="card-header">
        <h3>👥 All Employees <span style="font-size:12px;color:var(--color-muted);font-weight:400;margin-left:8px">{{ $employees->count() }} total</span></h3>
        <a href="{{ route('sync.run') }}" class="btn btn-ghost" style="font-size:12px" onclick="return confirm('Sync employees from EasyTime Pro?')">🔄 Sync</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Code</th>
                <th>Department</th>
                <th>Position</th>
                <th>Today's Status</th>
                <th>Last Seen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $emp)
                @php
                    $todayLog = $todayLogs->firstWhere('emp_code', $emp->emp_code);
                    $lastLog = $lastLogs->firstWhere('emp_code', $emp->emp_code);
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar">{{ strtoupper(substr($emp->first_name ?? $emp->emp_code, 0, 1)) }}</div>
                            <div>
                                <div style="font-weight:600;color:var(--color-text)">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                <div style="font-size:12px;color:var(--color-muted)">{{ $emp->emp_code }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $emp->emp_code }}</td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $emp->department ?? '—' }}</td>
                    <td style="font-size:13px;color:var(--color-muted)">{{ $emp->position ?? '—' }}</td>
                    <td>
                        @if($todayLog)
                            <span class="badge badge-present">● Present</span>
                        @else
                            <span class="badge badge-absent">● Absent</span>
                        @endif
                    </td>
                    <td style="font-size:13px;color:var(--color-muted)">
                        @if($lastLog)
                            {{ \Carbon\Carbon::parse($lastLog->punch_time)->diffForHumans() }}
                        @else
                            Never
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
