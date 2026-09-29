<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID13: 勤怠詳細情報取得・修正機能（管理者）
 *
 * NOTE: AdminController::editAttendance は App\Http\Requests\User\EditAttendanceRequest を
 * そのまま使い回しているため、バリデーションメッセージは ID11（一般ユーザー）と共通です。
 */
class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private function baseParams(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '管理者による修正',
        ], $overrides);
    }

    public function test_shows_the_selected_attendance_data(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create([
            'clock_in' => '2026-05-10 09:05:00',
            'clock_out' => '2026-05-10 18:10:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.showAttendance', $record->id));

        $response->assertOk();
        $response->assertSee('09:05');
        $response->assertSee('18:10');
    }

    public function test_error_when_clock_out_is_before_clock_in(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.editAttendance', $record->id),
            $this->baseParams(['new_clock_in' => '18:00', 'new_clock_out' => '09:00'])
        );

        $response->assertSessionHasErrors('new_clock_out');
        $this->assertSame('出勤時間もしくは退勤時間が不適切な値です', session('errors')->first('new_clock_out'));
    }

    public function test_error_when_break_start_is_after_clock_out(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.editAttendance', $record->id),
            $this->baseParams(['new_break_in' => ['19:00'], 'new_break_out' => ['19:30']])
        );

        $response->assertSessionHasErrors('new_break_in.0');
        $this->assertSame('休憩時間が不適切な値です', session('errors')->first('new_break_in.0'));
    }

    public function test_error_when_break_end_is_after_clock_out(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.editAttendance', $record->id),
            $this->baseParams(['new_break_in' => ['17:00'], 'new_break_out' => ['19:00']])
        );

        $response->assertSessionHasErrors('new_break_out.0');
        $this->assertSame('休憩時間もしくは退勤時間が不適切な値です', session('errors')->first('new_break_out.0'));
    }

    public function test_error_when_comment_is_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.editAttendance', $record->id),
            $this->baseParams(['comment' => ''])
        );

        $response->assertSessionHasErrors('comment');
        $this->assertSame('備考を記入してください', session('errors')->first('comment'));
    }

    public function test_editing_updates_the_attendance_record_and_logs_an_approved_request(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->create([
            'clock_in' => '2026-05-10 09:00:00',
            'clock_out' => '2026-05-10 18:00:00',
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.editAttendance', $record->id),
            $this->baseParams(['new_clock_in' => '10:00', 'new_clock_out' => '19:00'])
        );

        $response->assertRedirect(route('admin.showAttendance', $record->id));
        $record->refresh();
        $this->assertSame('10:00', $record->clock_in->format('H:i'));
        $this->assertSame('19:00', $record->clock_out->format('H:i'));
        $this->assertDatabaseHas('attendance_requests', [
            'attendance_record_id' => $record->id,
            'is_approved' => true,
        ]);
    }
}
