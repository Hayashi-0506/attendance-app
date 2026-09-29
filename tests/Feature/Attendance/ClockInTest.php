<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID6: 出勤機能
 */
class ClockInTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_in_button_is_shown_when_off_duty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertSee('出勤');
    }

    public function test_clocking_in_creates_a_record_and_changes_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('attendance.store'), ['action' => 'clock_in']);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => now()->toDateString(),
        ]);
        $this->assertSame('出勤中', $user->fresh()->attendance_status);
    }

    public function test_cannot_clock_in_twice_in_the_same_day(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->today()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(9),
            'clock_out' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('attendance.store'), ['action' => 'clock_in']);

        $response->assertStatus(422);
        $this->assertSame(1, AttendanceRecord::where('user_id', $user->id)->count());
    }

    public function test_clock_in_time_is_visible_on_the_attendance_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('attendance.store'), ['action' => 'clock_in']);

        $response = $this->actingAs($user)->get(route('attendance.attendanceList'));

        $record = AttendanceRecord::where('user_id', $user->id)->first();
        $response->assertSee($record->formatted_clock_in);
    }
}
