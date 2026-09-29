<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース一覧 ID9: 勤怠一覧情報取得機能（一般ユーザー）
 */
class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_shows_all_of_the_logged_in_users_attendance_records(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $user = User::factory()->create();
        $other = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-03',
            'clock_in' => '2026-05-03 09:00:00',
            'clock_out' => '2026-05-03 18:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $other->id,
            'date' => '2026-05-03',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.attendanceList'));

        $response->assertOk();
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_shows_the_current_month_by_default(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('attendance.attendanceList'));

        $response->assertViewHas('date', fn ($date) => $date->format('Y-m') === '2026-05');
    }

    public function test_previous_month_button_shows_the_previous_months_data(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-04-10',
            'clock_in' => '2026-04-10 09:00:00',
            'clock_out' => '2026-04-10 18:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.attendanceList', ['date' => '2026-04']));

        $response->assertOk();
        $response->assertViewHas('date', fn ($date) => $date->format('Y-m') === '2026-04');
        $response->assertSee('09:00');
    }

    public function test_next_month_button_shows_the_next_months_data(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.attendanceList', ['date' => '2026-06']));

        $response->assertOk();
        $response->assertViewHas('date', fn ($date) => $date->format('Y-m') === '2026-06');
        $response->assertSee('09:00');
    }

    public function test_detail_link_navigates_to_the_attendance_detail_page(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('attendance.showAttendance', $record->id));

        $response->assertOk();
        $response->assertViewIs('user.user-detail');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('attendance.attendanceList'));

        $response->assertRedirect(route('login'));
    }
}
