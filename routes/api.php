<?php

use App\Http\Controllers\Api\AttendanceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Attendance logs — filterable by emp_code, date, start_date, end_date
    Route::get('/attendance', [AttendanceApiController::class, 'index']);

    // Daily summary for a specific date
    Route::get('/attendance/summary/{date}', [AttendanceApiController::class, 'summary']);

    // All logs for a specific employee
    Route::get('/attendance/employee/{emp_code}', [AttendanceApiController::class, 'employee']);

    // Employees list
    Route::get('/employees', [AttendanceApiController::class, 'employees']);
});
