<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース一覧 ID14: ユーザー情報取得機能（管理者） 後半
 * （選択したユーザーの勤怠一覧・前月/翌月・詳細遷移）
 */
class StaffAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_shows_the_selected_users_attendance_correctly(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $staff->id,
            'date' => '2026-05-03',
            'clock_in' => '2026-05-03 09:00:00',
            'clock_out' => '2026-05-03 18:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.staffAttendanceList', $staff->id));

        $response->assertOk();
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_previous_month_button_shows_the_previous_months_data(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $staff->id,
            'date' => '2026-04-10',
            'clock_in' => '2026-04-10 09:00:00',
            'clock_out' => '2026-04-10 18:00:00',
        ]);

        $response = $this->actingAs($admin)->get(
            route('admin.staffAttendanceList', ['user' => $staff->id, 'date' => '2026-04'])
        );

        $response->assertOk();
        $response->assertViewHas('date', fn ($date) => $date->format('Y-m') === '2026-04');
        $response->assertSee('09:00');
    }

    public function test_next_month_button_shows_the_next_months_data(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $staff->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);

        $response = $this->actingAs($admin)->get(
            route('admin.staffAttendanceList', ['user' => $staff->id, 'date' => '2026-06'])
        );

        $response->assertOk();
        $response->assertViewHas('date', fn ($date) => $date->format('Y-m') === '2026-06');
        $response->assertSee('09:00');
    }

    public function test_detail_link_navigates_to_the_admin_attendance_detail_page(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.showAttendance', $record->id));

        $response->assertOk();
        $response->assertViewIs('admin.admin-detail');
    }
}
