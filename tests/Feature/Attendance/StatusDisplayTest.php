<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID5: ステータス確認機能
 */
class StatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_off_duty_status_is_shown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertSee('勤務外');
    }

    public function test_working_status_is_shown(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertSee('出勤中');
    }

    public function test_on_break_status_is_shown(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHour(),
        ]);
        BreakRecord::factory()->ongoing()->create(['attendance_record_id' => $record->id]);

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertSee('休憩中');
    }

    public function test_finished_status_is_shown(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(9),
            'clock_out' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertSee('退勤済');
    }
}
