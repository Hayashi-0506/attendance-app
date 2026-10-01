<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::name('attendance.')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('index');
        Route::post('attendance', [AttendanceController::class, 'store'])->name('store');
        Route::get('attendance/list', [AttendanceController::class, 'attendanceList'])->name('attendanceList');
        Route::get('attendance/report', [AttendanceController::class, 'report'])->name('report');
        Route::get('attendance/{attendanceRecord}', [AttendanceController::class, 'showAttendance'])->name('showAttendance');
        Route::post('attendance/{attendanceRecord}', [AttendanceController::class, 'edit'])->name('edit');
        Route::get('stamp_correction_request/list', [AttendanceController::class, 'applicationList'])->name('applicationList');
        Route::get('application/{attendanceRequest}', [AttendanceController::class, 'showApplication'])->name('showApplication');
    });
});

Route::name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('admin/login', [AdminController::class, 'create'])->name('login');
        Route::post('admin/login', [AdminController::class, 'store']);
    });

    Route::middleware(['auth', 'admin', 'verified'])->group(function () {
        Route::get('admin/attendance/list', [AdminController::class, 'dailyAttendanceList'])->name('dailyAttendanceList');
        Route::get('admin/attendance/{attendanceRecord}', [AdminController::class, 'showAttendance'])->name('showAttendance');
        Route::post('admin/attendance/{attendanceRecord}', [AdminController::class, 'editAttendance'])->name('editAttendance');
        Route::get('admin/staff/list', [AdminController::class, 'staffList'])->name('staffList');
        Route::get('admin/attendance/staff/{user}', [AdminController::class, 'staffAttendanceList'])->name('staffAttendanceList');
        Route::get('stamp_correction_request/approve/{attendanceRequest}', [AdminController::class, 'showRequest'])->name('showRequest');
        Route::post('stamp_correction_request/approve/{attendanceRequest}', [AdminController::class, 'approveRequest'])->name('approveRequest');
        Route::post('admin/logout', [AdminController::class, 'logout'])->name('logout');
        Route::post('export', [AdminController::class, 'export'])->name('export');
    });
});
