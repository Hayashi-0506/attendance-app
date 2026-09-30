<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース一覧 ID20: ★ マイ勤怠レポート機能
 */
class MyAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 6, 15));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_access_the_report_page(): void
    {
        $response = $this->get(route('attendance.report'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_statistics_are_calculated_correctly(): void
    {
        $user = User::factory()->create();
        // 9時間勤務(540分、残業60分)
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);
        // 遅刻(9:15出勤)かつ早退(17:30退勤)の勤務(8時間15分、残業15分)
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-11',
            'clock_in' => '2026-06-11 09:15:00',
            'clock_out' => '2026-06-11 17:30:00',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.report'));

        $response->assertOk();
        $response->assertViewIs('reports.index');
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_work_minutes'] === 540 + 495
                && $summary['total_overtime_minutes'] === 60 + 15
                && $summary['avg_work_minutes'] === intdiv(540 + 495, 2);
        });
        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 0,
        ]);
        $response->assertViewHas('monthlyTrend', function ($monthlyTrend) {
            return $monthlyTrend->count() === 6
                && $monthlyTrend->firstWhere('month', '2026-06')['work_minutes'] === 540 + 495;
        });
    }

    public function test_user_without_attendance_records_is_handled_safely(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('attendance.report'));

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);
        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
        $response->assertViewHas('monthlyTrend', function ($monthlyTrend) {
            return $monthlyTrend->count() === 6
                && $monthlyTrend->every(fn ($m) => $m['work_minutes'] === 0 && $m['overtime_minutes'] === 0);
        });
    }

    public function test_records_outside_the_six_month_window_do_not_affect_the_report(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-01',
            'clock_in' => '2025-01-01 09:00:00',
            'clock_out' => '2025-01-01 18:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.report'));

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);
    }
}
