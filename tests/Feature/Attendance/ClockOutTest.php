<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID8: 退勤機能
 */
class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_out_button_is_shown_while_working(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(8),
        ]);

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertSee('退勤');
    }

    public function test_clocking_out_updates_status_to_finished(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(8),
        ]);

        $response = $this->actingAs($user)->post(route('attendance.store'), ['action' => 'clock_out']);

        $response->assertRedirect();
        $this->assertNotNull($record->fresh()->clock_out);
        $this->assertSame('退勤済', $record->fresh()->attendance_status);
    }

    public function test_clock_out_time_is_visible_on_the_attendance_list(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(8),
        ]);
        $this->actingAs($user)->post(route('attendance.store'), ['action' => 'clock_out']);

        $response = $this->actingAs($user)->get(route('attendance.attendanceList'));

        $record = AttendanceRecord::where('user_id', $user->id)->first();
        $response->assertSee($record->formatted_clock_out);
    }
}
