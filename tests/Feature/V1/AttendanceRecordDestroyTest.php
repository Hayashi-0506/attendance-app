<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ID18: ★公開API（書き込み系） - 削除
 * DELETE /api/v1/attendance-records/{id}
 */
class AttendanceRecordDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_delete_their_own_attendance_record(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/attendance-records/{$record->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }

    public function test_deleting_a_nonexistent_record_returns_404_with_the_spec_error_body(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/attendance-records/999999');

        $response->assertStatus(404)->assertJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
