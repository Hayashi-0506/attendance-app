<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース一覧 ID20: ★ マイ勤怠レポート機能
 *
 * ReportService::getReportData() の集計ロジック単体テスト。
 * 「現在時刻」を軸に直近6ヶ月(REPORT_MONTHS)を集計するため、
 * Carbon::setTestNow() で基準日を2026-06-15に固定しています。
 * (集計対象期間: 2026-01-01 〜 2026-06-30)
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportService::class);
        Carbon::setTestNow(Carbon::create(2026, 6, 15));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_returns_zeroed_stats_when_user_has_no_attendance_records(): void
    {
        $user = User::factory()->create();

        $data = $this->service->getReportData($user);

        $this->assertSame([
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ], $data['summary']);
        $this->assertSame([
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ], $data['anomalies']);
        // 集計対象期間の6ヶ月分のバケットは0埋めで用意される
        $this->assertCount(6, $data['monthlyTrend']);
        $this->assertTrue($data['monthlyTrend']->every(
            fn ($month) => $month['work_minutes'] === 0 && $month['overtime_minutes'] === 0
        ));
    }

    public function test_calculates_total_and_average_work_minutes(): void
    {
        $user = User::factory()->create();
        // 9時間勤務(540分)と7時間勤務(420分)の2日分
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-11',
            'clock_in' => '2026-06-11 09:00:00',
            'clock_out' => '2026-06-11 16:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(540 + 420, $data['summary']['total_work_minutes']);
        $this->assertSame(intdiv(540 + 420, 2), $data['summary']['avg_work_minutes']);
    }

    public function test_calculates_overtime_minutes_over_eight_hours(): void
    {
        $user = User::factory()->create();
        // 9時間勤務 → 8時間(480分)を超えた60分が残業扱いになる
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(60, $data['summary']['total_overtime_minutes']);
    }

    public function test_exactly_nine_to_six_is_not_late_or_early_leave(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(0, $data['anomalies']['late_count']);
        $this->assertSame(0, $data['anomalies']['early_leave_count']);
    }

    public function test_counts_late_arrivals(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:15:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(1, $data['anomalies']['late_count']);
    }

    public function test_counts_early_leaves(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 17:30:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(1, $data['anomalies']['early_leave_count']);
    }

    public function test_counts_long_work_days_over_ten_hours(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 08:00:00',
            'clock_out' => '2026-06-10 20:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(1, $data['anomalies']['long_work_count']);
    }

    public function test_records_without_clock_out_are_excluded(): void
    {
        $user = User::factory()->create();
        // まだ退勤していない(今日の勤務中)レコードは集計対象外
        AttendanceRecord::factory()->notClockedOut()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(0, $data['summary']['total_work_minutes']);
        $this->assertSame(0, $data['summary']['avg_work_minutes']);
    }

    public function test_records_older_than_six_months_are_excluded(): void
    {
        $user = User::factory()->create();
        // 集計対象期間(2026-01〜2026-06)より前の記録
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-12-15',
            'clock_in' => '2025-12-15 09:00:00',
            'clock_out' => '2025-12-15 18:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $this->assertSame(0, $data['summary']['total_work_minutes']);
    }

    public function test_monthly_trend_covers_the_last_six_months_including_current(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'clock_in' => '2026-06-10 09:00:00',
            'clock_out' => '2026-06-10 18:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $months = $data['monthlyTrend']->pluck('month')->all();
        $this->assertSame(
            ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'],
            $months
        );
        $juneBucket = $data['monthlyTrend']->firstWhere('month', '2026-06');
        $this->assertSame(540, $juneBucket['work_minutes']);
    }

    public function test_multiple_records_in_the_same_month_are_summed_into_one_bucket(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-05',
            'clock_in' => '2026-05-05 09:00:00',
            'clock_out' => '2026-05-05 18:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-20',
            'clock_in' => '2026-05-20 09:00:00',
            'clock_out' => '2026-05-20 18:00:00',
        ]);

        $data = $this->service->getReportData($user);

        $mayBucket = $data['monthlyTrend']->firstWhere('month', '2026-05');
        $this->assertSame(540 * 2, $mayBucket['work_minutes']);
        $this->assertSame(60 * 2, $mayBucket['overtime_minutes']);
    }
}
