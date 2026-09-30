<?php

namespace Database\Factories;

use App\Models\AttendanceRequest;
use App\Models\BreakRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakRequest>
 */
class BreakRequestFactory extends Factory
{
    protected $model = BreakRequest::class;

    public function definition(): array
    {
        return [
            'attendance_request_id' => AttendanceRequest::factory(),
            'break_in' => now(),
            'break_out' => now()->addMinutes(60),
        ];
    }
}
