<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $date = fake()->date();

        return [
            'user_id' => User::factory(),
            'date' => $date,
            'clock_in' => $date.' 09:00:00',
            'clock_out' => $date.' 18:00:00',
        ];
    }

    /**
     * dateを今日にする
     */
    public function today(): static
    {
        return $this->state(fn () => ['date' => now()->toDateString()]);
    }

    /**
     * まだ退勤していない状態にする
     */
    public function notClockedOut(): static
    {
        return $this->state(fn () => ['clock_out' => null]);
    }

    /**
     * まだ出勤していない状態にする
     */
    public function notClockedIn(): static
    {
        return $this->state(fn () => [
            'clock_in' => null,
            'clock_out' => null,
        ]);
    }
}
