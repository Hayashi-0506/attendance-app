<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID11: 勤怠詳細情報修正機能（一般ユーザー）
 */
class CorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    private function baseParams(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '電車遅延のため',
        ], $overrides);
    }

    public function test_error_when_clock_out_is_before_clock_in(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('attendance.edit', $record->id),
            $this->baseParams(['new_clock_in' => '18:00', 'new_clock_out' => '09:00'])
        );

        $response->assertSessionHasErrors('new_clock_out');
        $this->assertSame('出勤時間もしくは退勤時間が不適切な値です', session('errors')->first('new_clock_out'));
    }

    public function test_error_when_break_start_is_after_clock_out(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('attendance.edit', $record->id),
            $this->baseParams(['new_break_in' => ['19:00'], 'new_break_out' => ['19:30']])
        );

        $response->assertSessionHasErrors('new_break_in.0');
        $this->assertSame('休憩時間が不適切な値です', session('errors')->first('new_break_in.0'));
    }

    public function test_error_when_break_end_is_after_clock_out(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('attendance.edit', $record->id),
            $this->baseParams(['new_break_in' => ['17:00'], 'new_break_out' => ['19:00']])
        );

        $response->assertSessionHasErrors('new_break_out.0');
        $this->assertSame('休憩時間もしくは退勤時間が不適切な値です', session('errors')->first('new_break_out.0'));
    }

    public function test_error_when_comment_is_blank(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('attendance.edit', $record->id),
            $this->baseParams(['comment' => ''])
        );

        $response->assertSessionHasErrors('comment');
        $this->assertSame('備考を記入してください', session('errors')->first('comment'));
    }

    public function test_submitting_a_correction_creates_a_pending_attendance_request(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('attendance.edit', $record->id),
            $this->baseParams()
        );

        $response->assertRedirect(route('attendance.showAttendance', $record->id));
        $this->assertDatabaseHas('attendance_requests', [
            'attendance_record_id' => $record->id,
            'user_id' => $user->id,
            'comment' => '電車遅延のため',
        ]);
    }

    public function test_own_pending_requests_are_listed_in_the_application_list(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('attendance.edit', $record->id), $this->baseParams());

        $response = $this->actingAs($user)->get(route('attendance.applicationList'));

        $response->assertOk();
        $response->assertSee('承認待ち');
        $response->assertSee('電車遅延のため');
    }

    public function test_approved_requests_are_listed_with_approved_status(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('attendance.edit', $record->id), $this->baseParams());
        $attendanceRequest = AttendanceRequest::where('attendance_record_id', $record->id)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.approveRequest', $attendanceRequest->id));

        $response = $this->actingAs($user)->get(route('attendance.applicationList'));

        $response->assertSee('承認済み');
    }

    public function test_application_detail_link_shows_the_attendance_detail(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('attendance.edit', $record->id), $this->baseParams());
        $attendanceRequest = AttendanceRequest::where('attendance_record_id', $record->id)->firstOrFail();

        $response = $this->actingAs($user)->get(route('attendance.showApplication', $attendanceRequest->id));

        $response->assertOk();
        $response->assertSee('電車遅延のため');
    }

    public function test_cannot_view_another_users_application(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner)->post(route('attendance.edit', $record->id), $this->baseParams());
        $attendanceRequest = AttendanceRequest::where('attendance_record_id', $record->id)->firstOrFail();

        $response = $this->actingAs($intruder)->get(route('attendance.showApplication', $attendanceRequest->id));

        $response->assertForbidden();
    }
}
