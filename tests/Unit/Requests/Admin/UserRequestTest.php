<?php

namespace Tests\Unit\Requests\Admin;

use App\Http\Requests\Admin\UserRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UserRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new UserRequest)->rules();
    }

    public function test_email_is_required(): void
    {
        $validator = Validator::make(['password' => 'password'], $this->rules());

        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_password_is_required(): void
    {
        $validator = Validator::make(['email' => 'admin@example.com'], $this->rules());

        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_valid_input_passes(): void
    {
        $validator = Validator::make(
            ['email' => 'admin@example.com', 'password' => 'password'],
            $this->rules()
        );

        $this->assertFalse($validator->fails());
    }
}
