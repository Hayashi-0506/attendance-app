<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\BreakRecord;
use App\Models\BreakRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID15: 勤怠情報修正機能（管理者）
 */
class CorrectionRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_requests_from_all_users_are_shown(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $recordA = AttendanceRecord::factory()->create(['user_id' => $userA->id]);
        $recordB = AttendanceRecord::factory()->create(['user_id' => $userB->id]);
        AttendanceRequest::factory()->create([
            'attendance_record_id' => $recordA->id,
            'user_id' => $userA->id,
            'is_approved' => false,
            'comment' => 'Aさんの申請',
        ]);
        AttendanceRequest::factory()->create([
            'attendance_record_id' => $recordB->id,
            'user_id' => $userB->id,
            'is_approved' => false,
            'comment' => 'Bさんの申請',
        ]);

        $response = $this->actingAs($admin)->get(route('attendance.applicationList'));

        $response->assertOk();
        $response->assertSee('Aさんの申請');
        $response->assertSee('Bさんの申請');
    }

    public function test_approved_requests_from_all_users_are_shown(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        AttendanceRequest::factory()->approved()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $user->id,
            'comment' => '承認済みの申請',
        ]);

        $response = $this->actingAs($admin)->get(route('attendance.applicationList'));

        $response->assertOk();
        $response->assertSee('承認済みの申請');
        $response->assertSee('承認済み');
    }

    public function test_request_detail_shows_correct_content(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        $request = AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $user->id,
            'comment' => '体調不良のため休憩を追加',
            'clock_in' => $record->date->format('Y-m-d').' 09:30:00',
            'clock_out' => $record->date->format('Y-m-d').' 18:30:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.showRequest', $request->id));

        $response->assertOk();
        $response->assertSee('体調不良のため休憩を追加');
        $response->assertSee('09:30');
        $response->assertSee('18:30');
    }

    public function test_approving_a_request_updates_the_attendance_record(): void
    {
        $admin = User::factory()->admin()->create();
        $record = AttendanceRecord::factory()->notClockedOut()->create([
            'clock_in' => '2026-06-01 09:00:00',
        ]);
        $existingBreak = BreakRecord::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '2026-06-01 12:00:00',
            'break_out' => '2026-06-01 13:00:00',
        ]);
        $request = AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'is_approved' => false,
            'clock_in' => '2026-06-01 09:30:00',
            'clock_out' => '2026-06-01 18:30:00',
        ]);
        BreakRequest::factory()->create([
            'attendance_request_id' => $request->id,
            'break_in' => '2026-06-01 12:15:00',
            'break_out' => '2026-06-01 13:15:00',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.approveRequest', $request->id));

        $response->assertRedirect(route('admin.showRequest', $request->id));
        $this->assertTrue((bool) $request->fresh()->is_approved);
        $record->refresh();
        $this->assertSame('09:30', $record->clock_in->format('H:i'));
        $this->assertSame('18:30', $record->clock_out->format('H:i'));
        $this->assertSame('12:15', $existingBreak->fresh()->break_in->format('H:i'));
    }

    public function test_non_admin_cannot_approve_requests(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        $request = AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('admin.approveRequest', $request->id));

        $response->assertRedirect('/admin/login');
        $this->assertFalse((bool) $request->fresh()->is_approved);
    }
}
