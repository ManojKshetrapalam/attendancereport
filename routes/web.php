<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceReportController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/attendance', [DashboardController::class, 'attendance'])->name('attendance.index');
Route::get('/attendance/export/{filename}.xlsx', [DashboardController::class, 'exportAttendance'])->name('attendance.export');
Route::get('/employees', [DashboardController::class, 'employees'])->name('employees.index');
Route::get('/sync', [DashboardController::class, 'syncNow'])->name('sync.run');

// Reports
Route::get('/reports/daily', [AttendanceReportController::class, 'daily'])->name('reports.daily');
Route::get('/reports/daily/export/{filename}.xlsx', [AttendanceReportController::class, 'exportDaily'])->name('reports.daily.export');
Route::get('/reports/monthly', [AttendanceReportController::class, 'monthly'])->name('reports.monthly');
Route::get('/reports/monthly/export/{filename}.xlsx', [AttendanceReportController::class, 'exportMonthly'])->name('reports.monthly.export');
