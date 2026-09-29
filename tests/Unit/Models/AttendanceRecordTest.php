<?php

namespace Tests\Unit\Models;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_off_duty_when_not_clocked_in(): void
    {
        $record = AttendanceRecord::factory()->notClockedIn()->make();

        $this->assertSame('勤務外', $record->attendance_status);
    }

    public function test_status_is_working_when_clocked_in_without_break_or_clock_out(): void
    {
        $record = AttendanceRecord::factory()->notClockedOut()->create([
            'clock_in' => now(),
        ]);

        $this->assertSame('出勤中', $record->attendance_status);
    }

    public function test_status_is_on_break_when_latest_break_has_no_break_out(): void
    {
        $record = AttendanceRecord::factory()->notClockedOut()->create([
            'clock_in' => now()->subHour(),
        ]);
        BreakRecord::factory()->ongoing()->create([
            'attendance_record_id' => $record->id,
            'break_in' => now(),
        ]);

        $this->assertSame('休憩中', $record->fresh()->attendance_status);
    }

    public function test_status_is_working_again_after_break_ends(): void
    {
        $record = AttendanceRecord::factory()->notClockedOut()->create([
            'clock_in' => now()->subHours(2),
        ]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => now()->subHour(),
            'break_out' => now()->subMinutes(30),
        ]);

        $this->assertSame('出勤中', $record->fresh()->attendance_status);
    }

    public function test_status_is_finished_when_clocked_out(): void
    {
        // ファクトリのデフォルトで clock_in / clock_out が設定済み
        $record = AttendanceRecord::factory()->create();

        $this->assertSame('退勤済', $record->attendance_status);
    }

    public function test_formatted_accessors(): void
    {
        $record = AttendanceRecord::factory()->create([
            'date' => '2026-05-10',
            'clock_in' => '2026-05-10 09:03:00',
            'clock_out' => '2026-05-10 18:07:00',
        ]);

        $this->assertSame('09:03', $record->formatted_clock_in);
        $this->assertSame('18:07', $record->formatted_clock_out);
        $this->assertStringContainsString('05月10日', $record->formatted_date);
    }

    public function test_total_break_time_sums_multiple_breaks(): void
    {
        $record = AttendanceRecord::factory()->create([
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '2026-05-10 12:00:00',
            'break_out' => '2026-05-10 13:00:00',
        ]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '2026-05-10 15:00:00',
            'break_out' => '2026-05-10 15:15:00',
        ]);

        // 60分 + 15分 = 75分
        $this->assertSame(75 * 60, $record->fresh()->total_break_time);
    }

    public function test_total_time_subtracts_break_time_from_working_time(): void
    {
        $record = AttendanceRecord::factory()->create([
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '2026-05-10 12:00:00',
            'break_out' => '2026-05-10 13:00:00',
        ]);

        // 9時間勤務 - 1時間休憩 = 8時間
        $this->assertSame(8 * 3600, $record->fresh()->total_time);
    }

    public function test_total_time_is_zero_when_not_clocked_out(): void
    {
        $record = AttendanceRecord::factory()->notClockedOut()->create([
            'clock_in' => now(),
        ]);

        $this->assertSame(0, $record->total_time);
    }

    public function test_format_seconds_to_hm(): void
    {
        $record = new AttendanceRecord;

        $this->assertSame('1:05', $record->formatSecondsToHM(3900));
        $this->assertSame('', $record->formatSecondsToHM(0));
        $this->assertSame('', $record->formatSecondsToHM(null));
    }
}
