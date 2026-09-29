<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID7: 休憩機能
 */
class BreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_break_is_reflected_in_status(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_in']);

        $response->assertRedirect();
        $this->assertSame('休憩中', $record->fresh()->attendance_status);
        $this->assertDatabaseHas('break_records', ['attendance_record_id' => $record->id]);
    }

    public function test_break_can_be_taken_multiple_times_a_day(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(4),
        ]);

        $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_in']);
        $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_out']);
        $response = $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_in']);

        $response->assertRedirect();
        $this->assertSame(2, BreakRecord::where('attendance_record_id', $record->id)->count());
        $this->assertSame('休憩中', $record->fresh()->attendance_status);
    }

    public function test_ending_a_break_returns_status_to_working(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(2),
        ]);
        $break = BreakRecord::factory()->ongoing()->create(['attendance_record_id' => $record->id]);

        $response = $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_out']);

        $response->assertRedirect();
        $this->assertNotNull($break->fresh()->break_out);
        $this->assertSame('出勤中', $record->fresh()->attendance_status);
    }

    public function test_break_out_can_be_repeated_across_multiple_break_cycles(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(5),
        ]);

        $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_in']);
        $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_out']);
        $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_in']);
        $response = $this->actingAs($user)->post(route('attendance.store'), ['action' => 'break_out']);

        $response->assertRedirect();
        $completed = BreakRecord::where('attendance_record_id', $record->id)
            ->whereNotNull('break_out')
            ->count();
        $this->assertSame(2, $completed);
    }

    public function test_break_times_are_visible_on_the_attendance_detail_page(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->today()->notClockedOut()->create([
            'user_id' => $user->id,
            'clock_in' => now()->subHours(3),
        ]);
        $break = BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => now()->subHours(2),
            'break_out' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->get(route('attendance.showAttendance', $record->id));

        $response->assertSee($break->break_in->format('H:i'));
        $response->assertSee($break->break_out->format('H:i'));
    }
}
