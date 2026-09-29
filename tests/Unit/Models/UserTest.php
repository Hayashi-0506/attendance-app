<?php

namespace Tests\Unit\Models;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_status_is_off_duty_when_no_record_for_today(): void
    {
        $user = User::factory()->create();

        $this->assertSame('勤務外', $user->attendance_status);
    }

    public function test_attendance_status_reflects_todays_attendance_record(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now(),
        ]);

        $this->assertSame('出勤中', $user->fresh()->attendance_status);
    }

    public function test_attendance_status_ignores_records_from_other_days(): void
    {
        $user = User::factory()->create();
        // 昨日の（退勤済の）記録しかない場合は「勤務外」
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame('勤務外', $user->fresh()->attendance_status);
    }

    public function test_user_has_many_attendance_records(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->attendanceRecords);
    }
}
