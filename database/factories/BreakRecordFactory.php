<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakRecord>
 */
class BreakRecordFactory extends Factory
{
    protected $model = BreakRecord::class;

    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'break_in' => now(),
            'break_out' => now()->addMinutes(60),
        ];
    }

    /**
     * 休憩中（まだ戻っていない）状態にする
     */
    public function ongoing(): static
    {
        return $this->state(fn () => ['break_out' => null]);
    }
}
