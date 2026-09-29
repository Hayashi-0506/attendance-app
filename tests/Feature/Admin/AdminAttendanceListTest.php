<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース一覧 ID12: 勤怠一覧情報取得機能（管理者）
 */
class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_shows_every_users_attendance_for_the_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 10));
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create(['name' => '山田太郎']);
        $userB = User::factory()->create(['name' => '鈴木花子']);
        AttendanceRecord::factory()->create([
            'user_id' => $userA->id,
            'date' => '2026-05-10',
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $userB->id,
            'date' => '2026-05-10',
            'clock_in' => '2026-05-10 10:00:00',
            'clock_out' => '2026-05-10 19:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dailyAttendanceList'));

        $response->assertOk();
        $response->assertSee('山田太郎');
        $response->assertSee('鈴木花子');
        $response->assertSee('09:00');
        $response->assertSee('10:00');
    }

    public function test_shows_todays_date_by_default(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 10));
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dailyAttendanceList'));

        $response->assertViewHas('date', fn ($date) => $date->format('Y-m-d') === '2026-05-10');
    }

    public function test_previous_day_button_shows_the_previous_days_data(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 10));
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-09',
            'clock_in' => '2026-05-09 09:15:00',
            'clock_out' => '2026-05-09 18:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dailyAttendanceList', ['date' => '2026-05-09']));

        $response->assertOk();
        $response->assertViewHas('date', fn ($date) => $date->format('Y-m-d') === '2026-05-09');
        $response->assertSee('09:15');
    }

    public function test_next_day_button_shows_the_next_days_data(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 10));
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-11',
            'clock_in' => '2026-05-11 09:45:00',
            'clock_out' => '2026-05-11 18:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dailyAttendanceList', ['date' => '2026-05-11']));

        $response->assertOk();
        $response->assertViewHas('date', fn ($date) => $date->format('Y-m-d') === '2026-05-11');
        $response->assertSee('09:45');
    }

    public function test_non_admin_user_is_redirected_to_admin_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.dailyAttendanceList'));

        $response->assertRedirect('/admin/login');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.dailyAttendanceList'));

        $response->assertRedirect(route('login'));
    }
}
