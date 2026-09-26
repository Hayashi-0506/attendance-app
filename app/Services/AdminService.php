<?php

namespace App\Services;

use App\Http\Requests\User\EditAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\BreakRecord;
use App\Models\BreakRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function resolveTargetMonth(?string $targetMonth): CarbonImmutable
    {
        return $targetMonth
            ? CarbonImmutable::createFromFormat('Y-m-d', $targetMonth.'-01')
            : now()->toImmutable();
    }

    public function getAttendanceDetail(AttendanceRecord $attendance): array
    {
        $attendance->load(['breakRecords']);

        return [
            'id' => $attendance->id,
            'year' => $attendance->date->format('Y年'),
            'date' => $attendance->date->format('n月j日'),
            'clock_in' => $attendance->clock_in?->format('H:i'),
            'clock_out' => $attendance->clock_out?->format('H:i'),
            'comment' => '',
            'breaks' => $attendance->breakRecords->map(fn ($break) => [
                'break_in' => $break->break_in?->format('H:i'),
                'break_out' => $break->break_out?->format('H:i'),
            ])->toArray(),
        ];
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

    public function approveAttendanceRequest(AttendanceRequest $attendanceRequest)
    {
        $attendanceRequest->load(['attendanceRecord.breakRecords', 'breakRequests']);
        $attendanceRecord = $attendanceRequest->attendanceRecord;

        DB::transaction(function () use ($attendanceRequest, $attendanceRecord) {
            $attendanceRequest->update([
                'is_approved' => true,
            ]);

            $attendanceRecord->update([
                'clock_in' => $attendanceRequest->clock_in,
                'clock_out' => $attendanceRequest->clock_out,
            ]);

            foreach ($attendanceRequest->breakRequests as $index => $break) {
                if (isset($attendanceRecord->breakRecords[$index])) {
                    $attendanceRecord->breakRecords[$index]->update([
                        'break_in' => $break->break_in,
                        'break_out' => $break->break_out,
                    ]);
                } else {
                    BreakRecord::create([
                        'attendance_record_id' => $attendanceRecord->id,
                        'break_in' => $break->break_in,
                        'break_out' => $break->break_out,
                    ]);
                }
            }
        });
    }

    public function updateAttendanceRecord(EditAttendanceRequest $request, AttendanceRecord $attendanceRecord)
    {
        $attendanceRecord->load(['breakRecords' => fn ($q) => $q->orderBy('id')]);

        $newBreakIn = $request->new_break_in;
        $newBreakOut = $request->new_break_out;

        // 前提: 既存件数 + 1件分の配列が渡ってくる
        abort_unless(
            count($newBreakIn) === $attendanceRecord->breakRecords->count() + 1
            && count($newBreakOut) === $attendanceRecord->breakRecords->count() + 1,
            422,
            '休憩データの件数が不正です。'
        );

        DB::transaction(function () use ($request, $attendanceRecord, $newBreakIn, $newBreakOut) {
            $date = Carbon::parse($attendanceRecord->date->format('Y-m-d'));

            $attendanceRecord->update([
                'clock_in' => $date->copy()->setTimeFromTimeString($request->new_clock_in),
                'clock_out' => $date->copy()->setTimeFromTimeString($request->new_clock_out),
            ]);

            foreach ($attendanceRecord->breakRecords as $index => $break) {
                $break->update([
                    'break_in' => $date->copy()->setTimeFromTimeString($newBreakIn[$index]),
                    'break_out' => $date->copy()->setTimeFromTimeString($newBreakOut[$index]),
                ]);
            }

            $latestIndex = count($newBreakIn) - 1;
            if (! empty($newBreakIn[$latestIndex]) && ! empty($newBreakOut[$latestIndex])) {
                BreakRecord::create([
                    'attendance_record_id' => $attendanceRecord->id,
                    'break_in' => $date->copy()->setTimeFromTimeString($newBreakIn[$latestIndex]),
                    'break_out' => $date->copy()->setTimeFromTimeString($newBreakOut[$latestIndex]),
                ]);
            }
        });
    }

    public function createAttendanceRequest(EditAttendanceRequest $request, AttendanceRecord $attendanceRecord)
    {
        DB::transaction(function () use ($request, $attendanceRecord) {
            $date = Carbon::parse($attendanceRecord->date->format('Y-m-d'));

            $attendanceRequest = AttendanceRequest::create([
                'attendance_record_id' => $attendanceRecord->id,
                'user_id' => $attendanceRecord->user_id,
                'is_approved' => true,
                'date' => $date,
                'clock_in' => $date->copy()->setTimeFromTimeString($request->new_clock_in),
                'clock_out' => $date->copy()->setTimeFromTimeString($request->new_clock_out),
                'comment' => $request->comment,
                'request_date' => now()->format('Y-m-d'),
            ]);

            foreach ($request->breaks as $break) {
                if (! empty($break['new_break_in']) && ! empty($break['new_break_out'])) {
                    BreakRequest::create([
                        'attendance_request_id' => $attendanceRequest->id,
                        'break_in' => $date->copy()->setTimeFromTimeString($break['new_break_in']),
                        'break_out' => $date->copy()->setTimeFromTimeString($break['new_break_out']),
                    ]);
                }
            }
        });
    }
}
