<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->emp_code) {
            return redirect()->route('dashboard')->with('error', 'No employee record linked to this account.');
        }

        $month      = $request->input('month', now()->format('Y-m'));
        $shiftStart = $request->input('shift_start', '09:30');
        $startDate  = Carbon::parse($month . '-01');
        $endDate    = $startDate->copy()->endOfMonth();
        $calcEndDate = $endDate->isFuture() ? now()->endOfDay() : $endDate;

        $logs = AttendanceLog::where('emp_code', $user->emp_code)
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->orderBy('punch_time', 'desc')
            ->get();

        $groupedLogs = $logs->groupBy(fn($l) => Carbon::parse($l->punch_time)->format('Y-m-d'));

        $workingDays = 0;
        $presentDays = 0;
        $lateDays    = 0;
        $totalHours  = 0;
        $dayCount    = 0;

        for ($d = $startDate->copy(); $d->lte($calcEndDate); $d->addDay()) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) $workingDays++;

            $dateStr = $d->format('Y-m-d');
            $dayLogs = $groupedLogs->get($dateStr, collect());
            
            if ($dayLogs->isNotEmpty()) {
                $presentDays++;
                $firstIn = $dayLogs->where('punch_state', '0')->sortBy('punch_time')->first();
                $lastOut = $dayLogs->where('punch_state', '1')->sortBy('punch_time')->last();

                if ($firstIn && Carbon::parse($firstIn->punch_time)->format('H:i') > $shiftStart) {
                    $lateDays++;
                }

                if ($firstIn && $lastOut) {
                    $totalHours += Carbon::parse($firstIn->punch_time)->diffInMinutes(Carbon::parse($lastOut->punch_time)) / 60;
                    $dayCount++;
                }
            }
        }

        $avgHours = $dayCount > 0 ? round($totalHours / $dayCount, 1) : null;

        return view('employee.dashboard', compact(
            'logs', 'groupedLogs', 'presentDays', 'lateDays', 'avgHours', 'workingDays', 'month', 'shiftStart'
        ));
    }
}
