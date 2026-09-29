<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * テストケース一覧 ID4: 日時取得機能
 */
class DateTimeDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_attendance_page_shows_the_current_date_and_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 10, 9, 30, 0));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
        $response->assertViewHas('formattedDate', '2026-05-10');
        $response->assertViewHas('formattedTime', '09:30:00');
    }

    public function test_guest_cannot_access_attendance_page(): void
    {
        $response = $this->get(route('attendance.index'));

        $response->assertRedirect(route('login'));
    }
}
