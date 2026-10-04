<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ID18: ★公開API（書き込み系） - 更新
 * PUT /api/v1/attendance-records/{id}
 */
class AttendanceRecordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_update_their_own_attendance_record(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'date' => '2026-05-10',
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_in' => '08:45:00',
            'comment' => '電車遅延のため',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'comment' => '電車遅延のため',
        ]);
        $this->assertSame('08:45:00', $record->refresh()->clock_in->format('H:i:s'));
    }

    public function test_updating_a_nonexistent_record_returns_404_with_the_spec_error_body(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/attendance-records/999999', [
            'clock_in' => '09:00:00',
        ]);

        // NOTE: show() のテストと同様、Handler.php の整備が必要です。
        $response->assertStatus(404)->assertJson(['error' => '勤怠情報が見つかりませんでした。']);
    }

    public function test_an_invalid_time_format_is_rejected(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_in' => '9:00',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('clock_in');
    }

    public function test_a_clock_out_not_after_the_resulting_clock_in_is_rejected(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'clock_in' => now()->setTime(9, 0),
            'clock_out' => now()->setTime(18, 0),
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_out' => '08:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('clock_out')
            ->assertJsonFragment(['退勤時刻は出勤時刻より後にしてください。']);
    }

    public function test_only_the_fields_sent_in_the_request_are_changed(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'date' => '2026-05-10',
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'comment' => 'コメントのみ更新',
        ]);

        $response->assertStatus(200);
        $record->refresh();
        $this->assertSame('09:00:00', $record->clock_in->format('H:i:s'));
        $this->assertSame('18:00:00', $record->clock_out->format('H:i:s'));
        $this->assertSame('コメントのみ更新', $record->comment);
    }
}
