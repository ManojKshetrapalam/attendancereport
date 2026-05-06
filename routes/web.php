<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Employee\AttendanceController as EmployeeAttendanceController;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin Routes (Protected by Auth and Admin Middleware)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/attendance', [DashboardController::class, 'attendance'])->name('attendance.index');
    Route::get('/attendance/export/{filename}.xlsx', [DashboardController::class, 'exportAttendance'])->name('attendance.export');
    Route::get('/employees', [DashboardController::class, 'employees'])->name('employees.index');
    Route::get('/sync', [DashboardController::class, 'syncNow'])->name('sync.run');

    // Reports
    Route::get('/reports/daily', [AttendanceReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/daily/export/{filename}.xlsx', [AttendanceReportController::class, 'exportDaily'])->name('reports.daily.export');
    Route::get('/reports/monthly', [AttendanceReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/monthly/drilldown', [AttendanceReportController::class, 'drilldown'])->name('reports.monthly.drilldown');
    Route::get('/reports/monthly/export/{filename}.xlsx', [AttendanceReportController::class, 'exportMonthly'])->name('reports.monthly.export');
});

// Employee Routes (Protected by Auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/my-attendance', [EmployeeAttendanceController::class, 'index'])->name('employee.dashboard');
});
