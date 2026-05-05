<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    protected string $shiftStart = '09:30';

    /**
     * GET /api/v1/attendance
     * Params: emp_code, date, start_date, end_date, page, per_page
     */
    public function index(Request $request)
    {
        $query = AttendanceLog::query()->orderBy('punch_time', 'desc');

        if ($request->filled('emp_code'))   $query->where('emp_code', $request->emp_code);
        if ($request->filled('date'))       $query->whereDate('punch_time', $request->date);
        if ($request->filled('start_date')) $query->where('punch_time', '>=', $request->start_date . ' 00:00:00');
        if ($request->filled('end_date'))   $query->where('punch_time', '<=', $request->end_date . ' 23:59:59');

        $perPage = min((int) $request->input('per_page', 100), 1000);
        $logs    = $query->paginate($perPage);

        return response()->json([
            'success'   => true,
            'total'     => $logs->total(),
            'page'      => $logs->currentPage(),
            'per_page'  => $perPage,
            'last_page' => $logs->lastPage(),
            'data'      => $logs->items(),
        ]);
    }

    /**
     * GET /api/v1/employees
     */
    public function employees()
    {
        return response()->json([
            'success' => true,
            'total'   => Employee::count(),
            'data'    => Employee::all(),
        ]);
    }

    /**
     * GET /api/v1/attendance/summary/{date}
     * Returns present/absent/late counts for a date
     */
    public function summary($date)
    {
        try {
            $d = Carbon::parse($date);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid date'], 400);
        }

        $employees = Employee::all();
        $logs      = AttendanceLog::whereDate('punch_time', $d->format('Y-m-d'))->get();

        $presentCodes = $logs->pluck('emp_code')->unique();
        $present      = $presentCodes->count();
        $absent       = max(0, $employees->count() - $present);

        // Late = first Check-In punch after shift start
        $late = 0;
        foreach ($presentCodes as $code) {
            $firstIn = $logs->where('emp_code', $code)->where('punch_state', '0')->sortBy('punch_time')->first();
            if ($firstIn && Carbon::parse($firstIn->punch_time)->format('H:i') > $this->shiftStart) {
                $late++;
            }
        }

        return response()->json([
            'success'      => true,
            'date'         => $d->format('Y-m-d'),
            'shift_start'  => $this->shiftStart,
            'total_employees' => $employees->count(),
            'present'      => $present,
            'absent'       => $absent,
            'late'         => $late,
            'on_time'      => $present - $late,
            'attendance_pct' => $employees->count() > 0 ? round(($present / $employees->count()) * 100, 1) : 0,
        ]);
    }

    /**
     * GET /api/v1/attendance/employee/{emp_code}
     * Returns all logs for one employee, optionally filtered by date range
     */
    public function employee(Request $request, $emp_code)
    {
        $emp = Employee::where('emp_code', $emp_code)->first();
        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $query = AttendanceLog::where('emp_code', $emp_code)->orderBy('punch_time', 'desc');
        if ($request->filled('start_date')) $query->where('punch_time', '>=', $request->start_date . ' 00:00:00');
        if ($request->filled('end_date'))   $query->where('punch_time', '<=', $request->end_date . ' 23:59:59');

        $logs = $query->paginate(100);

        return response()->json([
            'success'  => true,
            'employee' => $emp,
            'total'    => $logs->total(),
            'data'     => $logs->items(),
        ]);
    }
}
