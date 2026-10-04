<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    Route::get('attendance-records', [AttendanceRecordController::class, 'index']);
    Route::get('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show']);

    // 認証必須
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('attendance-records', [AttendanceRecordController::class, 'store']);
        Route::put('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::delete('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);
    });
});
