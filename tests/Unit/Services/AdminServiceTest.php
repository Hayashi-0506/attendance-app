<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\BreakRecord;
use App\Models\BreakRequest;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminService::class);
    }

    public function test_get_attendance_detail_returns_formatted_record(): void
    {
        $record = AttendanceRecord::factory()->create([
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 09:00:00',
            'clock_out' => '2026-06-01 18:00:00',
        ]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '2026-06-01 12:00:00',
            'break_out' => '2026-06-01 13:00:00',
        ]);

        $data = $this->service->getAttendanceDetail($record);

        $this->assertSame('09:00', $data['clock_in']);
        $this->assertSame('18:00', $data['clock_out']);
        $this->assertCount(1, $data['breaks']);
        $this->assertSame('12:00', $data['breaks'][0]['break_in']);
    }

    public function test_resolve_target_month_defaults_to_current_month(): void
    {
        $result = $this->service->resolveTargetMonth(null);

        $this->assertSame(now()->format('Y-m'), $result->format('Y-m'));
    }

    public function test_get_monthly_attendance_records_for_staff(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-04-05',
            'clock_in' => '2026-04-05 09:00:00',
            'clock_out' => '2026-04-05 18:00:00',
        ]);

        $date = $this->service->resolveTargetMonth('2026-04');
        $records = $this->service->getMonthlyAttendanceRecords($user, $date);

        $this->assertCount(30, $records);
    }

    public function test_approve_attendance_request_updates_attendance_record_and_breaks(): void
    {
        $record = AttendanceRecord::factory()->notClockedOut()->create([
            'clock_in' => '2026-06-01 09:00:00',
        ]);
        $existingBreak = BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '2026-06-01 12:00:00',
            'break_out' => '2026-06-01 13:00:00',
        ]);
        $request = AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'is_approved' => false,
            'clock_in' => '2026-06-01 09:30:00',
            'clock_out' => '2026-06-01 18:30:00',
        ]);
        BreakRequest::factory()->create([
            'attendance_request_id' => $request->id,
            'break_in' => '2026-06-01 12:15:00',
            'break_out' => '2026-06-01 13:15:00',
        ]);

        $this->service->approveAttendanceRequest($request);

        $this->assertTrue((bool) $request->fresh()->is_approved);
        $record->refresh();
        $this->assertSame('09:30', $record->clock_in->format('H:i'));
        $this->assertSame('18:30', $record->clock_out->format('H:i'));
        $this->assertSame('12:15', $existingBreak->fresh()->break_in->format('H:i'));
        $this->assertSame('13:15', $existingBreak->fresh()->break_out->format('H:i'));
    }

    public function test_approve_attendance_request_creates_new_break_records_when_more_breaks_are_requested(): void
    {
        $record = AttendanceRecord::factory()->notClockedOut()->create([
            'clock_in' => '2026-06-01 09:00:00',
        ]);
        // 既存の休憩レコードは0件、申請には休憩を1件追加
        $request = AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'is_approved' => false,
            'clock_in' => '2026-06-01 09:00:00',
            'clock_out' => '2026-06-01 18:00:00',
        ]);
        BreakRequest::factory()->create([
            'attendance_request_id' => $request->id,
            'break_in' => '2026-06-01 12:00:00',
            'break_out' => '2026-06-01 13:00:00',
        ]);

        $this->service->approveAttendanceRequest($request);

        $this->assertDatabaseHas('break_records', [
            'attendance_record_id' => $record->id,
            'break_in' => '2026-06-01 12:00:00',
            'break_out' => '2026-06-01 13:00:00',
        ]);
    }
}
