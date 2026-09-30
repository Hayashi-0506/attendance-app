<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRequest>
 */
class AttendanceRequestFactory extends Factory
{
    protected $model = AttendanceRequest::class;

    public function definition(): array
    {
        $date = fake()->date();

        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'user_id' => User::factory(),
            'is_approved' => false,
            'date' => $date,
            'clock_in' => $date.' 09:00:00',
            'clock_out' => $date.' 18:00:00',
            'comment' => fake()->sentence(),
            'request_date' => now()->toDateString(),
        ];
    }

    /**
     * 承認済みの状態にする
     */
    public function approved(): static
    {
        return $this->state(fn () => ['is_approved' => true]);
    }
}
