<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ID17: ★公開API（読み取り系） - 詳細取得
 * GET /api/v1/attendance-records/{id}
 */
class AttendanceRecordShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_fetch_a_single_attendance_record_with_its_relations(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();
        BreakRecord::factory()->for($record, 'attendanceRecord')->create();

        $response = $this->getJson("/api/v1/attendance-records/{$record->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonStructure([
                'data' => ['id', 'user', 'breaks', 'applications'],
            ])
            ->assertJsonCount(1, 'data.breaks');
    }

    public function test_fetching_a_nonexistent_attendance_record_returns_404_with_the_spec_error_body(): void
    {
        $response = $this->getJson('/api/v1/attendance-records/999999');

        $response->assertStatus(404)
            ->assertJson(['error' => '勤怠情報が見つかりませんでした。']);
    }

    public function test_fetching_a_record_that_has_not_clocked_out_yet_does_not_error(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->notClockedOut()->create();

        $response = $this->getJson("/api/v1/attendance-records/{$record->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.clock_out', '');
    }
}
