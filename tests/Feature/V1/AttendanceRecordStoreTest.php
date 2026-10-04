<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ID18: ★公開API（書き込み系） - 新規作成
 * POST /api/v1/attendance-records
 */
class AttendanceRecordStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_create_an_attendance_record(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '直行直帰のため',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'comment' => '直行直帰のため',
        ]);
    }

    public function test_the_record_is_created_without_a_comment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-10',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-05-10',
        ]);
    }

    public function test_missing_required_fields_return_a_422_with_japanese_messages(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'clock_in'])
            ->assertJsonFragment(['勤怠日は必須です。'])
            ->assertJsonFragment(['出勤時刻は必須です。']);
    }

    public function test_a_duplicate_date_for_the_same_user_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-05-10']);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-10',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('date')
            ->assertJsonFragment(['この日付の勤怠は既に登録されています。']);
    }

    public function test_the_same_date_is_allowed_for_a_different_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        AttendanceRecord::factory()->for($userA)->create(['date' => '2026-05-10']);
        Sanctum::actingAs($userB);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-10',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(201);
    }

    public function test_a_clock_out_before_clock_in_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-10',
            'clock_in' => '18:00:00',
            'clock_out' => '09:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('clock_out')
            ->assertJsonFragment(['退勤時刻は出勤時刻より後の時刻を指定してください。']);
    }

    public function test_an_invalid_date_format_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026/05/10',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('date');
    }
}
