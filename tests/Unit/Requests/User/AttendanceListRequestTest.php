<?php

namespace Tests\Unit\Requests\User;

use App\Http\Requests\User\AttendanceListRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AttendanceListRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new AttendanceListRequest)->rules();
    }

    public function test_date_is_optional(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_date_must_be_year_month_format(): void
    {
        $validator = Validator::make(['date' => '2026-05-01'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_valid_year_month_passes(): void
    {
        $validator = Validator::make(['date' => '2026-05'], $this->rules());

        $this->assertFalse($validator->fails());
    }
}
