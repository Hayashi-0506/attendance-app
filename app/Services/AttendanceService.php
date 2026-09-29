<?php

namespace App\Services;

use App\Http\Requests\User\EditAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
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
                $attendanceRecord = $attendanceRecords->get($dateString);
                $formattedAttendanceRecords->push([
                    'id' => $attendanceRecord->id,
                    'date' => $attendanceRecord->formatted_date,
                    'clock_in' => $attendanceRecord->formatted_clock_in,
                    'clock_out' => $attendanceRecord->formatted_clock_out,
                    'total_break_time' => $attendanceRecord->formatSecondsToHM($attendanceRecord->total_break_time),
                    'total_time' => $attendanceRecord->formatSecondsToHM($attendanceRecord->total_time),
                ]);
            } else {
                $formattedAttendanceRecords->push([
                    'id' => null,
                    'date' => Carbon::parse($dateString)->isoFormat('MM月DD日(ddd)'),
                    'clock_in' => null,
                    'clock_out' => null,
                    'total_break_time' => null,
                    'total_time' => null,
                ]);
            }
        }

        return $formattedAttendanceRecords;
    }

    public function getAttendanceDetail(AttendanceRecord $attendance): array
    {
        $attendance->load([
            'breakRecords',
            'pendingAttendanceRequest.breakRequests',
        ]);

        $hasRequest = (bool) $attendance->pendingAttendanceRequest;

        // 表示に使うデータソースを先に決定する
        $source = $hasRequest ? $attendance->pendingAttendanceRequest : $attendance;
        $breaks = $hasRequest ? $attendance->pendingAttendanceRequest->breakRequests : $attendance->breakRecords;

        return [
            'id' => $attendance->id,
            'year' => $attendance->date->format('Y年'),
            'date' => $attendance->date->format('n月j日'),
            'clock_in' => $source->clock_in?->format('H:i'),
            'clock_out' => $source->clock_out?->format('H:i'),
            'application' => $hasRequest ? $attendance->pendingAttendanceRequest->request_status : null,
            'comment' => $hasRequest ? $attendance->pendingAttendanceRequest->comment : '',
            'breaks' => $breaks->map(fn ($break) => [
                'break_in' => $break->break_in?->format('H:i'),
                'break_out' => $break->break_out?->format('H:i'),
            ])->toArray(),
        ];
    }

    public function createAttendanceRequest(EditAttendanceRequest $request, AttendanceRecord $attendanceRecord)
    {
        DB::transaction(function () use ($request, $attendanceRecord) {
            $date = Carbon::parse($attendanceRecord->date->format('Y-m-d'));

            $attendanceRequest = AttendanceRequest::create([
                'attendance_record_id' => $attendanceRecord->id,
                'user_id' => auth()->id(),
                'date' => $date,
                'clock_in' => $date->copy()->setTimeFromTimeString($request->new_clock_in),
                'clock_out' => $date->copy()->setTimeFromTimeString($request->new_clock_out),
                'comment' => $request->comment,
                'request_date' => now(),
            ]);

            foreach ($request->breaks as $break) {
                if (! empty($break['new_break_in']) && ! empty($break['new_break_out'])) {
                    $attendanceRequest->breakRequests()->create([
                        'attendance_request_id' => $attendanceRequest->id,
                        'break_in' => $date->copy()->setTimeFromTimeString($break['new_break_in']),
                        'break_out' => $date->copy()->setTimeFromTimeString($break['new_break_out']),
                    ]);
                }
            }
        });
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
            'application' => $request->request_status,
            'comment' => $request->comment,
            'breaks' => $request->breakRequests->map(fn ($break) => [
                'break_in' => $break->break_in?->format('H:i'),
                'break_out' => $break->break_out?->format('H:i'),
            ])->toArray(),
        ];
    }
}
