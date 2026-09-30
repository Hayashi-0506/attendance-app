<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;

class ReportService
{
    public const DEFAULT_WORKING_MINUTES = 8 * 60;

    public const LONG_WORKING_MINUTES = 10 * 60;

    public const REPORT_MONTHS = 6;

    public function getReportData(User $user): array
    {
        $baseDate = Carbon::now();
        $startDate = $baseDate->copy()->startOfMonth()->subMonths(self::REPORT_MONTHS - 1);
        $endDate = $baseDate->copy()->endOfMonth();

        $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->orderBy('date')
            ->get();

        $monthly = [];
        for ($month = $startDate->copy(); $month->lte($endDate); $month->addMonth()) {
            $monthly[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'work_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        $totalWorkMinutes = 0;
        $totalOvertimeMinutes = 0;
        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        foreach ($attendanceRecords as $record) {
            $workMinutes = intdiv($record->total_time, 60);
            $overtime = max(0, $workMinutes - self::DEFAULT_WORKING_MINUTES);

            $key = $record->date->format('Y-m');
            $monthly[$key]['work_minutes'] += $workMinutes;
            $monthly[$key]['overtime_minutes'] += $overtime;

            if ($record->clock_in->gt($record->date->copy()->setTime(9, 0))) {
                $lateCount++;
            }
            if ($record->clock_out->lt($record->date->copy()->setTime(18, 0))) {
                $earlyLeaveCount++;
            }
            if ($workMinutes > self::LONG_WORKING_MINUTES) {
                $longWorkCount++;
            }

            $totalWorkMinutes += $workMinutes;
            $totalOvertimeMinutes += $overtime;
        }

        $workDays = $attendanceRecords->count();

        return [
            'summary' => [
                'total_work_minutes' => $totalWorkMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'avg_work_minutes' => $workDays > 0 ? intdiv($totalWorkMinutes, $workDays) : 0,
            ],
            'monthlyTrend' => collect(array_values($monthly)),
            'anomalies' => [
                'late_count' => $lateCount,
                'early_leave_count' => $earlyLeaveCount,
                'long_work_count' => $longWorkCount,
            ],
        ];
    }
}
