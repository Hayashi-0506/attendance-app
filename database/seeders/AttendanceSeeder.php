<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * 勤怠パターン定義 [出勤時刻, 退勤時刻]
     */
    private const PATTERNS = [
        'normal' => ['09:00', '18:00'], // 通常
        'overtime' => ['09:00', '20:00'], // 残業
        'late' => ['09:30', '18:00'], // 遅刻
        'early' => ['09:00', '17:00'], // 早退
        'long' => ['08:00', '21:00'], // 長時間労働
    ];

    private const BREAK_START = '12:00';

    private const BREAK_END = '13:00';

    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->firstOrFail();

        $this->seedPastFiveMonths($user1);
        $this->seedCurrentMonth($user1);
    }

    /**
     * 過去5ヶ月分: 各月 平日15日 の通常勤務(9:00-18:00)
     */
    private function seedPastFiveMonths(User $user): void
    {
        for ($i = 5; $i >= 1; $i--) {
            $monthStart = now()->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $weekdays = $this->weekdaysOf($monthStart, $monthEnd);

            $selected = collect($weekdays)->shuffle()->take(15);

            foreach ($selected as $date) {
                $this->createRecord($user, $date, 'normal');
            }
        }
    }

    /**
     * 当月分: 平日17日を
     * 通常10 / 残業3 / 遅刻2 / 早退1 / 長時間労働1 に振り分け
     */
    private function seedCurrentMonth(User $user): void
    {
        $monthStart = now()->copy()->startOfMonth();
        $monthEnd = now()->copy()->endOfMonth();

        $weekdays = $this->weekdaysOf($monthStart, $monthEnd);

        if (count($weekdays) < 17) {
            throw new \RuntimeException(
                '当月の平日数が17日に足りません。対象月を調整してください。'
            );
        }

        $selected = collect($weekdays)->shuffle()->take(17)->values();

        $patterns = collect([
            ...array_fill(0, 10, 'normal'),
            ...array_fill(0, 3, 'overtime'),
            ...array_fill(0, 2, 'late'),
            ...array_fill(0, 1, 'early'),
            ...array_fill(0, 1, 'long'),
        ])->shuffle()->values();

        foreach ($selected as $index => $date) {
            $this->createRecord($user, $date, $patterns[$index]);
        }
    }

    /**
     * 指定期間内の平日一覧を取得
     */
    private function weekdaysOf(Carbon $start, Carbon $end): array
    {
        $period = CarbonPeriod::create($start, $end);

        return collect($period)
            ->filter(fn (Carbon $date) => $date->isWeekday())
            ->values()
            ->all();
    }

    /**
     * 勤怠レコードを作成(user1は固定休憩 12:00-13:00 を必ず付与)
     */
    private function createRecord(User $user, Carbon $date, string $pattern): void
    {
        [$clockIn, $clockOut] = self::PATTERNS[$pattern];

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'clock_in' => $date->copy()->setTimeFromTimeString($clockIn),
            'clock_out' => $date->copy()->setTimeFromTimeString($clockOut),
        ]);

        BreakRecord::create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_in' => $date->copy()->setTimeFromTimeString(self::BREAK_START),
            'break_out' => $date->copy()->setTimeFromTimeString(self::BREAK_END),
        ]);
    }
}
