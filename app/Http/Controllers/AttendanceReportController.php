<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    public function daily(Request $request)
    {
        $date       = $request->filled('date') ? Carbon::parse($request->date) : today();
        $shiftStart = $request->input('shift_start', '09:30');
        $employees  = Employee::all();

        $dayLogs = AttendanceLog::whereDate('punch_time', $date->format('Y-m-d'))
            ->orderBy('punch_time')
            ->get()
            ->groupBy('emp_code');

        $report = [];
        foreach ($employees as $emp) {
            $logs       = $dayLogs->get($emp->emp_code, collect());
            $present    = $logs->isNotEmpty();
            $firstIn    = $logs->where('punch_state', '0')->first();
            $lastOut    = $logs->where('punch_state', '1')->last();
            $hoursWorked = null;
            if ($firstIn && $lastOut) {
                $hoursWorked = round(Carbon::parse($firstIn->punch_time)->diffInMinutes(Carbon::parse($lastOut->punch_time)) / 60, 1);
            }
            $late = $present && $firstIn && Carbon::parse($firstIn->punch_time)->format('H:i') > $shiftStart;

            $report[] = [
                'emp_code'    => $emp->emp_code,
                'name'        => trim($emp->first_name . ' ' . $emp->last_name),
                'department'  => $emp->department ?? '—',
                'present'     => $present,
                'late'        => $late,
                'first_in'    => $firstIn ? Carbon::parse($firstIn->punch_time) : null,
                'last_out'    => $lastOut ? Carbon::parse($lastOut->punch_time) : null,
                'hours_worked'=> $hoursWorked,
                'punch_count' => $logs->count(),
            ];
        }

        $summary = [
            'total'   => $employees->count(),
            'present' => collect($report)->where('present', true)->count(),
            'absent'  => collect($report)->where('present', false)->count(),
            'late'    => collect($report)->where('late', true)->count(),
        ];

        return view('reports.daily', compact('date', 'shiftStart', 'report', 'summary'));
    }

    public function exportDaily(Request $request)
    {
        $date       = $request->filled('date') ? Carbon::parse($request->date) : today();
        $shiftStart = $request->input('shift_start', '09:30');
        $employees  = Employee::all();

        $dayLogs = AttendanceLog::whereDate('punch_time', $date->format('Y-m-d'))
            ->orderBy('punch_time')->get()->groupBy('emp_code');

        $filename = 'daily_report_' . $date->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($employees, $dayLogs, $shiftStart) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Emp Code', 'Name', 'Department', 'Status', 'First In', 'Last Out', 'Hours Worked', 'Late', 'Punch Count']);
            foreach ($employees as $emp) {
                $logs    = $dayLogs->get($emp->emp_code, collect());
                $present = $logs->isNotEmpty();
                $firstIn = $logs->where('punch_state', '0')->first();
                $lastOut = $logs->where('punch_state', '1')->last();
                $hours   = ($firstIn && $lastOut) ? round(Carbon::parse($firstIn->punch_time)->diffInMinutes(Carbon::parse($lastOut->punch_time)) / 60, 1) : '';
                $late    = $present && $firstIn && Carbon::parse($firstIn->punch_time)->format('H:i') > $shiftStart;
                fputcsv($file, [
                    $emp->emp_code,
                    trim($emp->first_name . ' ' . $emp->last_name),
                    $emp->department ?? '',
                    $present ? 'Present' : 'Absent',
                    $firstIn ? Carbon::parse($firstIn->punch_time)->format('h:i A') : '',
                    $lastOut ? Carbon::parse($lastOut->punch_time)->format('h:i A') : '',
                    $hours,
                    $late ? 'Yes' : 'No',
                    $logs->count(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function monthly(Request $request)
    {
        $month      = $request->input('month', now()->format('Y-m'));
        $shiftStart = $request->input('shift_start', '09:30');
        $startDate  = Carbon::parse($month . '-01');
        $endDate    = $startDate->copy()->endOfMonth();

        // Working days (Mon–Sat, excluding Sundays)
        $workingDays = 0;
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) $workingDays++;
        }

        $employees = Employee::all();
        $allLogs   = AttendanceLog::whereBetween('punch_time', [$startDate, $endDate])
            ->orderBy('punch_time')
            ->get()
            ->groupBy('emp_code');

        $report = [];
        foreach ($employees as $emp) {
            $empLogs    = $allLogs->get($emp->emp_code, collect())->groupBy(fn($l) => Carbon::parse($l->punch_time)->format('Y-m-d'));
            $presentDays= $empLogs->count();
            $lateDays   = 0;
            $totalHours = 0;
            $dayCount   = 0;

            foreach ($empLogs as $dayStr => $dayLogs) {
                $day = Carbon::parse($dayStr);
                if ($day->dayOfWeek === Carbon::SUNDAY) continue;
                $firstIn = $dayLogs->where('punch_state', '0')->first();
                $lastOut = $dayLogs->where('punch_state', '1')->last();
                if ($firstIn && Carbon::parse($firstIn->punch_time)->format('H:i') > $shiftStart) $lateDays++;
                if ($firstIn && $lastOut) {
                    $totalHours += Carbon::parse($firstIn->punch_time)->diffInMinutes(Carbon::parse($lastOut->punch_time)) / 60;
                    $dayCount++;
                }
            }

            $report[] = [
                'emp_code'    => $emp->emp_code,
                'name'        => trim($emp->first_name . ' ' . $emp->last_name),
                'department'  => $emp->department ?? '—',
                'present_days'=> $presentDays,
                'absent_days' => max(0, $workingDays - $presentDays),
                'late_days'   => $lateDays,
                'avg_hours'   => $dayCount > 0 ? round($totalHours / $dayCount, 1) : null,
            ];
        }

        // Sort by present days descending
        usort($report, fn($a, $b) => $b['present_days'] <=> $a['present_days']);

        // Daily trend: count distinct employees present per day
        $dailyTrend = [];
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            if ($d->dayOfWeek === Carbon::SUNDAY) continue;
            $key = $d->format('d');
            $dailyTrend[$key] = 0;
            foreach ($allLogs as $empCode => $logs) {
                if ($logs->whereStrict('punch_time', null)->isEmpty() &&
                    $logs->filter(fn($l) => Carbon::parse($l->punch_time)->format('Y-m-d') === $d->format('Y-m-d'))->isNotEmpty()) {
                    $dailyTrend[$key]++;
                }
            }
        }

        // Simpler daily trend approach
        $dailyTrend = [];
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            if ($d->dayOfWeek === Carbon::SUNDAY) continue;
            $dateStr = $d->format('Y-m-d');
            $count = 0;
            foreach ($allLogs as $logs) {
                if ($logs->filter(fn($l) => str_starts_with($l->punch_time, $dateStr))->isNotEmpty()) {
                    $count++;
                }
            }
            $dailyTrend[$d->format('d')] = $count;
        }

        $topAttendees   = array_slice($report, 0, 5);
        $totalLateCount = array_sum(array_column($report, 'late_days'));
        $totalAbsences  = array_sum(array_column($report, 'absent_days'));
        $totalEmployees = $employees->count();

        return view('reports.monthly', compact(
            'month', 'shiftStart', 'report', 'workingDays',
            'totalEmployees', 'dailyTrend', 'topAttendees',
            'totalLateCount', 'totalAbsences'
        ));
    }

    public function exportMonthly(Request $request)
    {
        // Re-use monthly logic and stream CSV
        $month      = $request->input('month', now()->format('Y-m'));
        $shiftStart = $request->input('shift_start', '09:30');
        $startDate  = Carbon::parse($month . '-01');
        $endDate    = $startDate->copy()->endOfMonth();

        $workingDays = 0;
        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) $workingDays++;
        }

        $employees = Employee::all();
        $allLogs   = AttendanceLog::whereBetween('punch_time', [$startDate, $endDate])
            ->get()->groupBy('emp_code');

        $filename = 'monthly_report_' . $month . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($employees, $allLogs, $workingDays, $shiftStart) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Emp Code', 'Name', 'Department', 'Present Days', 'Absent Days', 'Late Arrivals', 'Avg Hours/Day', 'Attendance %']);
            foreach ($employees as $emp) {
                $empLogs   = $allLogs->get($emp->emp_code, collect())->groupBy(fn($l) => Carbon::parse($l->punch_time)->format('Y-m-d'));
                $present   = $empLogs->count();
                $lateDays  = 0;
                $totalH    = 0;
                $dc        = 0;
                foreach ($empLogs as $dayLogs) {
                    $fi = $dayLogs->where('punch_state', '0')->first();
                    $lo = $dayLogs->where('punch_state', '1')->last();
                    if ($fi && Carbon::parse($fi->punch_time)->format('H:i') > $shiftStart) $lateDays++;
                    if ($fi && $lo) { $totalH += Carbon::parse($fi->punch_time)->diffInMinutes(Carbon::parse($lo->punch_time)) / 60; $dc++; }
                }
                $pct = $workingDays > 0 ? round(($present / $workingDays) * 100) : 0;
                fputcsv($file, [
                    $emp->emp_code,
                    trim($emp->first_name . ' ' . $emp->last_name),
                    $emp->department ?? '',
                    $present,
                    max(0, $workingDays - $present),
                    $lateDays,
                    $dc > 0 ? round($totalH / $dc, 1) : '',
                    $pct . '%',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
