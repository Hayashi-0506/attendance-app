<?php

namespace Tests\Unit\Models;

use App\Models\AttendanceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_status_is_pending_when_not_approved(): void
    {
        $request = AttendanceRequest::factory()->create(['is_approved' => false]);

        $this->assertSame('承認待ち', $request->request_status);
    }

    public function test_request_status_is_approved_when_approved(): void
    {
        $request = AttendanceRequest::factory()->approved()->create();

        $this->assertSame('承認済み', $request->request_status);
    }
}
