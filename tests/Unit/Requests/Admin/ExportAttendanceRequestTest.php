<?php

namespace Tests\Unit\Requests\Admin;

use App\Http\Requests\Admin\ExportAttendanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ExportAttendanceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new ExportAttendanceRequest)->rules();
    }

    public function test_user_id_must_exist(): void
    {
        $validator = Validator::make(['user_id' => 999999, 'year_month' => '2026-05'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_year_month_format_is_validated(): void
    {
        $user = User::factory()->create();

        $validator = Validator::make(['user_id' => $user->id, 'year_month' => '2026/05'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_valid_input_passes(): void
    {
        $user = User::factory()->create();

        $validator = Validator::make(
            ['user_id' => $user->id, 'year_month' => '2026-05'],
            $this->rules()
        );

        $this->assertFalse($validator->fails());
    }
}
