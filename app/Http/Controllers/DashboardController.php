<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected string $shiftStart = '09:30';

    public function index()
    {
        $today = today()->format('Y-m-d');
        $employees = Employee::all();
        $totalEmployees = $employees->count();

        // Today's punches
        $todayPunches = AttendanceLog::whereDate('punch_time', $today)
            ->orderBy('punch_time')
            ->get();

        $presentCodes = $todayPunches->pluck('emp_code')->unique();
        $todayPresent = $presentCodes->count();
        $todayAbsent  = max(0, $totalEmployees - $todayPresent);

        // Late arrivals — first IN punch after shift start
        $todayLate = AttendanceLog::whereDate('punch_time', $today)
            ->where('punch_state', '0')
            ->whereTime('punch_time', '>', $this->shiftStart)
            ->whereIn('emp_code', function ($q) use ($today) {
                // Only count as late if this is their FIRST check-in
                $q->selectRaw('MIN(punch_time)')
                  ->from('attendance_logs')
                  ->whereDate('punch_time', $today)
                  ->where('punch_state', '0')
                  ->groupBy('emp_code');
            })
            ->distinct('emp_code')
            ->count();

        // Absent employees
        $absentEmployees = $employees->whereNotIn('emp_code', $presentCodes->toArray());

        // Hourly distribution of today's punches (0-23)
        $hourlyData = array_fill(6, 15, 0); // show hours 6-20
        foreach ($todayPunches as $punch) {
            $hour = Carbon::parse($punch->punch_time)->hour;
            if ($hour >= 6 && $hour <= 20) {
                $hourlyData[$hour] = ($hourlyData[$hour] ?? 0) + 1;
            }
        }
        ksort($hourlyData);

        return view('dashboard.index', [
            'employees'        => $employees,
            'totalEmployees'   => $totalEmployees,
            'todayPresent'     => $todayPresent,
            'todayAbsent'      => $todayAbsent,
            'todayLate'        => $todayLate,
            'totalLogs'        => AttendanceLog::count(),
            'todayPunches'     => $todayPunches->take(50),
            'absentEmployees'  => $absentEmployees,
            'hourlyData'       => $hourlyData,
            'shiftStart'       => $this->shiftStart,
        ]);
    }

    public function attendance(Request $request)
    {
        $query = AttendanceLog::query()->orderBy('punch_time', 'desc');

        if ($request->filled('date')) {
            $query->whereDate('punch_time', $request->date);
        }
        if ($request->filled('emp_code')) {
            $query->where('emp_code', $request->emp_code);
        }
        if ($request->filled('punch_state')) {
            $query->where('punch_state', $request->punch_state);
        }

        $logs      = $query->paginate(50)->appends($request->all());
        $employees = Employee::all()->keyBy('emp_code');

        return view('dashboard.attendance', compact('logs', 'employees'));
    }

    public function exportAttendance(Request $request)
    {
        $query = AttendanceLog::query()->orderBy('punch_time', 'desc');

        if ($request->filled('date'))        $query->whereDate('punch_time', $request->date);
        if ($request->filled('emp_code'))    $query->where('emp_code', $request->emp_code);
        if ($request->filled('punch_state')) $query->where('punch_state', $request->punch_state);

        $logs      = $query->get();
        $employees = Employee::all()->keyBy('emp_code');

        $filename = 'attendance_' . ($request->date ?? today()->format('Y-m-d')) . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs, $employees) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee Code', 'Name', 'Department', 'Punch Time', 'Type', 'Terminal']);
            foreach ($logs as $log) {
                $emp = $employees->get($log->emp_code);
                fputcsv($file, [
                    $log->emp_code,
                    trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')),
                    $emp->department ?? '',
                    $log->punch_time,
                    $log->punch_state == '0' ? 'Check In' : ($log->punch_state == '1' ? 'Check Out' : 'Break'),
                    $log->terminal_alias ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function employees()
    {
        $employees = Employee::all();
        $today     = today()->format('Y-m-d');

        $todayLogs = AttendanceLog::whereDate('punch_time', $today)->get();
        $lastLogs  = AttendanceLog::select('emp_code', \DB::raw('MAX(punch_time) as punch_time'))
            ->groupBy('emp_code')
            ->get();

        return view('dashboard.employees', compact('employees', 'todayLogs', 'lastLogs'));
    }

    public function syncNow()
    {
        try {
            \Artisan::call('attendance:sync');
            return redirect()->back()->with('success', 'Sync completed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }
}
