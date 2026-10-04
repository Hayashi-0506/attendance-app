<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ID19: ★Sanctum認証
 */
class AttendanceRecordSanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_create_an_attendance_record(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-10',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_guest_cannot_update_an_attendance_record(): void
    {
        $record = AttendanceRecord::factory()->for(User::factory())->create();

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_guest_cannot_delete_an_attendance_record(): void
    {
        $record = AttendanceRecord::factory()->for(User::factory())->create();

        $response = $this->deleteJson("/api/v1/attendance-records/{$record->id}");

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_guest_can_still_read_records_without_a_token(): void
    {
        $record = AttendanceRecord::factory()->for(User::factory())->create();

        $this->getJson('/api/v1/attendance-records')->assertStatus(200);
        $this->getJson("/api/v1/attendance-records/{$record->id}")->assertStatus(200);
    }

    public function test_a_user_cannot_update_another_users_attendance_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $record = AttendanceRecord::factory()->for($owner)->create();
        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(403)->assertJson(['error' => 'この操作を実行する権限がありません。']);
    }

    public function test_a_user_cannot_delete_another_users_attendance_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $record = AttendanceRecord::factory()->for($owner)->create();
        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/v1/attendance-records/{$record->id}");

        $response->assertStatus(403)->assertJson(['error' => 'この操作を実行する権限がありません。']);
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id]);
    }

    public function test_an_admin_can_update_any_users_attendance_record(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->for($owner)->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_in' => '08:30:00',
        ]);

        $response->assertStatus(200);
    }

    public function test_an_admin_can_delete_any_users_attendance_record(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->for($owner)->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/attendance-records/{$record->id}");

        $response->assertStatus(204);
    }
}
