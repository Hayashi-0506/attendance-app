<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\User\StoreAttendanceRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreAttendanceRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new StoreAttendanceRequest)->rules();
    }

    public function test_action_is_required(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('action', $validator->errors()->toArray());
    }

    public function test_action_must_be_one_of_the_allowed_values(): void
    {
        $validator = Validator::make(['action' => 'invalid_action'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    /**
     * @dataProvider validActionsProvider
     */
    public function test_valid_actions_pass(string $action): void
    {
        $validator = Validator::make(['action' => $action], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public static function validActionsProvider(): array
    {
        return [
            ['clock_in'],
            ['clock_out'],
            ['break_in'],
            ['break_out'],
        ];
    }
}
