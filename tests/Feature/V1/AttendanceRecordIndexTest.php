<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ID17: ★公開API（読み取り系） - 一覧取得
 * GET /api/v1/attendance-records
 */
class AttendanceRecordIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_fetch_the_attendance_record_list(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->count(3)->for($user)->create();

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'user_id', 'user', 'date', 'clock_in', 'clock_out',
                        'total_time', 'total_break_time', 'comment', 'breaks',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_the_list_can_be_filtered_by_user_id(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        AttendanceRecord::factory()->for($userA)->create();
        AttendanceRecord::factory()->for($userB)->create();

        $response = $this->getJson("/api/v1/attendance-records?user_id={$userA->id}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($userA->id, $response->json('data.0.user_id'));
    }

    public function test_the_list_can_be_filtered_by_date(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-05-10']);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-05-11']);

        $response = $this->getJson('/api/v1/attendance-records?date=2026-05-10');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('2026-05-10', $response->json('data.0.date'));
    }

    public function test_the_list_can_be_filtered_by_month(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-05-10']);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-06-01']);

        $response = $this->getJson('/api/v1/attendance-records?month=2026-05');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('2026-05-10', $response->json('data.0.date'));
    }

    public function test_pagination_meta_reflects_the_per_page_parameter(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->count(5)->for($user)->create();

        $response = $this->getJson('/api/v1/attendance-records?per_page=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_an_out_of_range_per_page_parameter_returns_a_validation_error(): void
    {
        $response = $this->getJson('/api/v1/attendance-records?per_page=101');

        $response->assertStatus(422)->assertJsonValidationErrors('per_page');
    }

    public function test_clock_in_and_clock_out_are_formatted_as_hour_colon_minute(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->for($user)->create([
            'date' => '2026-05-10',
            'clock_in' => '2026-05-10 09:30:00',
            'clock_out' => '2026-05-10 18:15:00',
        ]);

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200);
        $this->assertSame('09:30', $response->json('data.0.clock_in'));
        $this->assertSame('18:15', $response->json('data.0.clock_out'));
    }
}
