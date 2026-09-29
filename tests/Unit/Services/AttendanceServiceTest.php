<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\BreakRecord;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);
    }

    public function test_clock_in_creates_todays_attendance_record(): void
    {
        $user = User::factory()->create();

        $this->service->clockIn($user);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => now()->toDateString(),
        ]);
        $record = AttendanceRecord::where('user_id', $user->id)->first();
        $this->assertNotNull($record->clock_in);
        $this->assertNull($record->clock_out);
    }

    public function test_clock_in_fails_when_already_clocked_in_today(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(9),
            'clock_out' => now(),
        ]);

        $this->expectException(HttpException::class);
        $this->service->clockIn($user);
    }

    public function test_clock_out_updates_todays_attendance_record(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(3),
        ]);

        $this->service->clockOut($user);

        $this->assertNotNull($record->fresh()->clock_out);
    }

    public function test_clock_out_fails_when_not_currently_working(): void
    {
        $user = User::factory()->create();

        $this->expectException(HttpException::class);
        $this->service->clockOut($user);
    }

    public function test_clock_out_fails_while_on_break(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(2),
        ]);
        BreakRecord::factory()->ongoing()->create(['attendance_record_id' => $record->id]);

        $this->expectException(HttpException::class);
        $this->service->clockOut($user);
    }

    public function test_start_break_creates_break_record_while_working(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHour(),
        ]);

        $this->service->startBreak($user);

        $this->assertDatabaseHas('break_records', [
            'attendance_record_id' => $record->id,
        ]);
        $this->assertSame('休憩中', $record->fresh()->attendance_status);
    }

    public function test_start_break_fails_when_not_working(): void
    {
        $user = User::factory()->create();

        $this->expectException(HttpException::class);
        $this->service->startBreak($user);
    }

    public function test_start_break_allows_multiple_breaks_per_day(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(3),
        ]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => now()->subHours(2),
            'break_out' => now()->subHours(1)->subMinutes(30),
        ]);

        $this->service->startBreak($user);

        $this->assertSame(2, BreakRecord::where('attendance_record_id', $record->id)->count());
    }

    public function test_end_break_updates_latest_break_record(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(2),
        ]);
        $break = BreakRecord::factory()->ongoing()->create(['attendance_record_id' => $record->id]);

        $this->service->endBreak($user);

        $this->assertNotNull($break->fresh()->break_out);
        $this->assertSame('出勤中', $record->fresh()->attendance_status);
    }

    public function test_end_break_fails_when_not_on_break(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now(),
        ]);

        $this->expectException(HttpException::class);
        $this->service->endBreak($user);
    }

    public function test_resolve_target_month_defaults_to_current_month(): void
    {
        $result = $this->service->resolveTargetMonth(null);

        $this->assertSame(now()->format('Y-m'), $result->format('Y-m'));
    }

    public function test_resolve_target_month_parses_given_month(): void
    {
        $result = $this->service->resolveTargetMonth('2026-03');

        $this->assertSame('2026-03', $result->format('Y-m'));
    }

    public function test_get_monthly_attendance_records_lists_every_day_of_month(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-04-10',
            'clock_in' => '2026-04-10 09:00:00',
            'clock_out' => '2026-04-10 18:00:00',
        ]);

        $date = $this->service->resolveTargetMonth('2026-04');
        $records = $this->service->getMonthlyAttendanceRecords($user, $date);

        $this->assertCount(30, $records);
        $withData = $records->firstWhere('id', '!=', null);
        $this->assertNotNull($withData);
        $this->assertSame('09:00', $withData['clock_in']);
    }

    public function test_get_attendance_detail_returns_actual_record_when_no_pending_request(): void
    {
        $record = AttendanceRecord::factory()->create([
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);

        $data = $this->service->getAttendanceDetail($record);

        $this->assertSame('09:00', $data['clock_in']);
        $this->assertSame('18:00', $data['clock_out']);
        $this->assertNull($data['application']);
        $this->assertSame('', $data['comment']);
    }

    public function test_get_attendance_detail_returns_pending_request_values_when_present(): void
    {
        $record = AttendanceRecord::factory()->create([
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);
        AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'is_approved' => false,
            'clock_in' => '2026-05-10 10:00:00',
            'clock_out' => '2026-05-10 19:00:00',
            'comment' => '電車遅延のため',
        ]);

        $data = $this->service->getAttendanceDetail($record->fresh());

        $this->assertSame('10:00', $data['clock_in']);
        $this->assertSame('19:00', $data['clock_out']);
        $this->assertSame('承認待ち', $data['application']);
        $this->assertSame('電車遅延のため', $data['comment']);
    }
}
