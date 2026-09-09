<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function clockIn(User $user): void
    {
        DB::transaction(function () use ($user) {
            $attendance = $this->lockTodayAttendance($user);
            $status = $attendance?->attendance_status ?? '勤務外';

            abort_if($status !== '勤務外', 422, '既に出勤済みです。');

            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => now()->toDateString(),
                'clock_in' => now(),
            ]);
        });
    }

    public function clockOut(User $user): void
    {
        DB::transaction(function () use ($user) {
            $attendance = $this->lockTodayAttendance($user);
            $status = $attendance?->attendance_status ?? '勤務外';

            abort_if($status !== '出勤中', 422, '出勤中ではありません。');

            $attendance->update(['clock_out' => now()]);
        });
    }

    public function startBreak(User $user): void
    {
        DB::transaction(function () use ($user) {
            $attendance = $this->lockTodayAttendance($user);
            $status = $attendance?->attendance_status ?? '勤務外';

            abort_if($status !== '出勤中', 422, '出勤中ではありません。');

            BreakRecord::create([
                'attendance_record_id' => $attendance->id,
                'break_in' => now(),
            ]);
        });
    }

    public function endBreak(User $user): void
    {
        DB::transaction(function () use ($user) {
            $attendance = $this->lockTodayAttendance($user);
            $status = $attendance?->attendance_status ?? '勤務外';

            abort_if($status !== '休憩中', 422, '休憩中ではありません。');

            $attendance->latestBreakRecord->update(['break_out' => now()]);
        });
    }

    private function lockTodayAttendance(User $user): ?AttendanceRecord
    {
        return AttendanceRecord::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->lockForUpdate()
            ->first();
    }

    public function resolveTargetMonth(?string $targetMonth): CarbonImmutable
    {
        return $targetMonth
            ? CarbonImmutable::createFromFormat('Y-m-d', $targetMonth.'-01')
            : now()->toImmutable();
    }

    public function getMonthlyAttendanceRecords(User $user, CarbonImmutable $date): Collection
    {
        $datesOfMonth = CarbonPeriod::create($date->startOfMonth(), $date->endOfMonth());
        $formattedAttendanceRecords = collect();

        $attendanceRecords = AttendanceRecord::with('breakRecords')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn ($record) => $record->date->format('Y-m-d'));

        foreach ($datesOfMonth as $targetDate) {
            $dateString = $targetDate->format('Y-m-d');

            if ($attendanceRecords->has($dateString)) {
                $formattedAttendanceRecords->push($attendanceRecords->get($dateString));
            } else {
                $formattedAttendanceRecords->push(
                    new AttendanceRecord([
                        'date' => $dateString,
                    ])
                );
            }
        }

        return $formattedAttendanceRecords;
    }

    public function getAttendanceDetail(AttendanceRecord $attendance): array
    {
        $attendance->load([
            'breakRecords',
            'approvedAttendanceRequests.breakRequests',
        ]);

        $hasRequest = (bool) $attendance->approvedAttendanceRequests;

        // 表示に使うデータソースを先に決定する
        $source = $hasRequest ? $attendance->approvedAttendanceRequests : $attendance;
        $breaks = $hasRequest ? $attendance->approvedAttendanceRequests->breakRequests : $attendance->breakRecords;

        return [
            'id' => $attendance->id,
            'year' => $attendance->date->format('Y年'),
            'date' => $attendance->date->format('n月j日'),
            'clock_in' => $source->clock_in?->format('H:i'),
            'clock_out' => $source->clock_out?->format('H:i'),
            'application' => $hasRequest ? $attendance->approvedAttendanceRequests->approval_status : null,
            'comment' => $hasRequest ? $attendance->approvedAttendanceRequests->comment : '',
            'breaks' => $breaks->map(fn ($break) => [
                'break_in' => $break->break_in?->format('H:i'),
                'break_out' => $break->break_out?->format('H:i'),
            ])->toArray(),
        ];
    }

    public function getApplicationDetail(AttendanceRequest $request): array
    {
        $request->load(['attendanceRecord', 'breakRequests']);
        $attendance = $request->attendanceRecord;

        return [
            'id' => $attendance->id,
            'year' => $attendance->date->format('Y年'),
            'date' => $attendance->date->format('n月j日'),
            'clock_in' => $request->clock_in?->format('H:i'),
            'clock_out' => $request->clock_out?->format('H:i'),
            'application' => $request->approval_status,
            'comment' => $request->comment,
            'breaks' => $request->breakRequests->map(fn ($break) => [
                'break_in' => $break->break_in?->format('H:i'),
                'break_out' => $break->break_out?->format('H:i'),
            ])->toArray(),
        ];
    }
}
