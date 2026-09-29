<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID10: 勤怠詳細情報取得機能（一般ユーザー）
 */
class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_shown_is_the_logged_in_users_name(): void
    {
        $user = User::factory()->create(['name' => '山田太郎']);
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('attendance.showAttendance', $record->id));

        $response->assertSee('山田太郎');
    }

    public function test_date_shown_matches_the_selected_record(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-10',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.showAttendance', $record->id));

        $response->assertSee('5月10日');
    }

    public function test_clock_in_and_out_times_match_the_users_punches(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'clock_in' => '2026-05-10 09:12:00',
            'clock_out' => '2026-05-10 18:05:00',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.showAttendance', $record->id));

        $response->assertSee('09:12');
        $response->assertSee('18:05');
    }

    public function test_break_times_match_the_users_punches(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => $record->date->format('Y-m-d').' 12:30:00',
            'break_out' => $record->date->format('Y-m-d').' 13:10:00',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.showAttendance', $record->id));

        $response->assertSee('12:30');
        $response->assertSee('13:10');
    }

    public function test_cannot_view_another_users_attendance_detail(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->get(route('attendance.showAttendance', $record->id));

        $response->assertForbidden();
    }
}
