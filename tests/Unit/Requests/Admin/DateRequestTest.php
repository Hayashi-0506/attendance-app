<?php

namespace Tests\Unit\Requests\Admin;

use App\Http\Requests\Admin\DateRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DateRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new DateRequest)->rules();
    }

    public function test_date_is_optional(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_date_accepts_ymd_format(): void
    {
        $validator = Validator::make(['date' => '2026-05-10'], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_date_accepts_ym_format(): void
    {
        $validator = Validator::make(['date' => '2026-05'], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_invalid_date_format_fails(): void
    {
        $validator = Validator::make(['date' => '2026/05/10'], $this->rules());

        $this->assertTrue($validator->fails());
    }
}
