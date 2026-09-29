<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\CustomLoginRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomLoginRequestTest extends TestCase
{
    private CustomLoginRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new CustomLoginRequest;
    }

    public function test_email_is_required(): void
    {
        $validator = Validator::make(
            ['password' => 'password'],
            $this->request->rules(),
            $this->request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('メールアドレスを入力してください', $validator->errors()->first('email'));
    }

    public function test_password_is_required(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com'],
            $this->request->rules(),
            $this->request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('パスワードを入力してください', $validator->errors()->first('password'));
    }

    public function test_valid_input_passes(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com', 'password' => 'password'],
            $this->request->rules(),
            $this->request->messages()
        );

        $this->assertFalse($validator->fails());
    }
}
