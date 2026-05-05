<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\ExcelExportService;
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

        // Corrected Late arrivals — only first punch of the day after shift start
        $allFirstPunches = AttendanceLog::whereDate('punch_time', $today)
            ->where('punch_state', '0')
            ->orderBy('punch_time', 'asc')
            ->get()
            ->groupBy('emp_code')
            ->map(fn($group) => $group->first());

        $lateArrivals = $allFirstPunches->filter(function($punch) {
            return Carbon::parse($punch->punch_time)->format('H:i') > $this->shiftStart;
        });
        
        $todayLate = $lateArrivals->count();

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

        // Weekly Trend (last 7 days)
        $weeklyTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i)->format('Y-m-d');
            $count = AttendanceLog::whereDate('punch_time', $date)->distinct('emp_code')->count();
            $weeklyTrend[today()->subDays($i)->format('D, d M')] = $count;
        }

        // Department Distribution
        $deptDistribution = Employee::select('department', \DB::raw('count(*) as count'))
            ->groupBy('department')
            ->get()
            ->pluck('count', 'department');

        // Shift Compliance (First arrival before 09:30 AM)
        $onTimeArrivals = $allFirstPunches->filter(function($punch) {
            return Carbon::parse($punch->punch_time)->format('H:i') <= $this->shiftStart;
        });
        $onTimeCount = $onTimeArrivals->count();
        
        $complianceRate = $todayPresent > 0 ? round(($onTimeCount / $todayPresent) * 100) : 0;

        // Average Late Minutes
        $totalLateMinutes = 0;
        $shiftStartTime = Carbon::parse($today . ' ' . $this->shiftStart);
        foreach ($lateArrivals as $log) {
            $punchTime = Carbon::parse($log->punch_time);
            $totalLateMinutes += max(0, $punchTime->diffInMinutes($shiftStartTime));
        }
        $avgLateMinutes = $todayLate > 0 ? round($totalLateMinutes / $todayLate) : 0;

        // Drilldown Data
        $drilldownTitle = null;
        $drilldownData = collect();
        $view = request('view');

        if ($view === 'present') {
            $drilldownTitle = "Present Employees Today";
            $drilldownData = $employees->whereIn('emp_code', $presentCodes->toArray())->map(function($emp) use ($allFirstPunches) {
                $punch = $allFirstPunches->get($emp->emp_code);
                $emp->status_info = $punch ? 'Arrived at ' . Carbon::parse($punch->punch_time)->format('h:i A') : 'Present';
                return $emp;
            });
        } elseif ($view === 'absent') {
            $drilldownTitle = "Absent Employees Today";
            $drilldownData = $absentEmployees;
        } elseif ($view === 'late') {
            $drilldownTitle = "Late Arrivals Today";
            $drilldownData = $employees->whereIn('emp_code', $lateArrivals->pluck('emp_code')->toArray())->map(function($emp) use ($allFirstPunches, $shiftStartTime) {
                $punch = $allFirstPunches->get($emp->emp_code);
                $delay = Carbon::parse($punch->punch_time)->diffInMinutes($shiftStartTime);
                $emp->status_info = "Arrived at " . Carbon::parse($punch->punch_time)->format('h:i A') . " ({$delay}m late)";
                return $emp;
            });
        }

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
            'weeklyTrend'      => $weeklyTrend,
            'deptDistribution' => $deptDistribution,
            'complianceRate'   => $complianceRate,
            'avgLateMinutes'   => $avgLateMinutes,
            'drilldownTitle'   => $drilldownTitle,
            'drilldownData'    => $drilldownData,
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

    public function exportAttendance(Request $request, $filename)
    {
        $query = AttendanceLog::query()->orderBy('punch_time', 'desc');

        if ($request->filled('date'))        $query->whereDate('punch_time', $request->date);
        if ($request->filled('emp_code'))    $query->where('emp_code', $request->emp_code);
        if ($request->filled('punch_state')) $query->where('punch_state', $request->punch_state);

        $logs      = $query->get();
        $employees = Employee::all()->keyBy('emp_code');
        $dateLabel = $request->date ?? today()->format('Y-m-d');

        $excel = new ExcelExportService();
        $cols  = ['Emp Code', 'Name', 'Department', 'Punch Time', 'Punch Type', 'Terminal'];

        $excel->addTitleRow(
            'AttendanceIQ — Punch Records',
            'Date: ' . $dateLabel . '  |  Generated: ' . now()->format('d M Y, h:i A'),
            count($cols)
        );
        $excel->applyHeaders('Attendance Logs', $cols, 3);

        $row = 4;
        foreach ($logs as $log) {
            $emp     = $employees->get($log->emp_code);
            $type    = $log->punch_state == '0' ? 'Check In' : ($log->punch_state == '1' ? 'Check Out' : 'Break');
            $excel->writeRow($row, [
                $log->emp_code,
                trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')),
                $emp->department ?? '',
                Carbon::parse($log->punch_time)->format('d/m/Y h:i A'),
                $type,
                $log->terminal_alias ?? '',
            ], $type);
            $row++;
        }

        return $excel->download('attendance_' . $dateLabel . '.xlsx');
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
