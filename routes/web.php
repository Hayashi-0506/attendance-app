<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('attendance/list', [AttendanceController::class, 'attendanceList'])->name('attendance.attendanceList');
    Route::get('attendance/{attendanceRecord}', [AttendanceController::class, 'showAttendance'])->name('attendance.showAttendance');
    Route::post('attendance/{attendanceRecord}', [AttendanceController::class, 'edit'])->name('attendance.edit');
    Route::get('stamp_correction_request/list', [AttendanceController::class, 'applicationList'])->name('attendance.applicationList');
    Route::get('application/{attendanceRequest}', [AttendanceController::class, 'showApplication'])->name('attendance.showApplication');
});
