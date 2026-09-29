<?php

namespace Tests\Unit\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\User;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private RequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RequestService::class);
    }

    public function test_application_list_shows_user_view_for_general_user(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $record = AttendanceRecord::factory()->create(['user_id' => $user->id]);
        AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $user->id,
        ]);

        $view = $this->service->getApplicationList();

        $this->assertSame('user.user-application-list', $view->getName());
        $this->assertCount(1, $view->getData()['formattedApplications']);
    }

    public function test_application_list_shows_admin_view_for_admin_user(): void
    {
        $admin = User::factory()->admin()->create();
        Auth::login($admin);
        $otherUser = User::factory()->create();
        $record = AttendanceRecord::factory()->create(['user_id' => $otherUser->id]);
        AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $otherUser->id,
        ]);

        $view = $this->service->getApplicationList();

        $this->assertSame('admin.admin-application-list', $view->getName());
        $this->assertCount(1, $view->getData()['applications']);
    }

    public function test_get_application_detail_returns_expected_shape(): void
    {
        $record = AttendanceRecord::factory()->create();
        $request = AttendanceRequest::factory()->create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'comment' => 'テストコメント',
            'clock_in' => $record->date->format('Y-m-d').' 09:30:00',
            'clock_out' => $record->date->format('Y-m-d').' 18:30:00',
        ]);

        $detail = $this->service->getApplicationDetail($request);

        $this->assertSame('09:30', $detail->new_clock_in);
        $this->assertSame('18:30', $detail->new_clock_out);
        $this->assertSame('テストコメント', $detail->comment);
        $this->assertSame('承認待ち', $detail->approval_status);
    }
}
