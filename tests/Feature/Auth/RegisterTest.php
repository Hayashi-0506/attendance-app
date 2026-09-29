<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID1: 認証機能（一般ユーザー）
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validParams(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_name_is_required(): void
    {
        $response = $this->post(route('register'), $this->validParams(['name' => '']));

        $response->assertSessionHasErrors('name');
        $this->assertSame('お名前を入力してください', session('errors')->first('name'));
    }

    public function test_email_is_required(): void
    {
        $response = $this->post(route('register'), $this->validParams(['email' => '']));

        $response->assertSessionHasErrors('email');
        $this->assertSame('メールアドレスを入力してください', session('errors')->first('email'));
    }

    public function test_password_must_be_at_least_8_characters(): void
    {
        $response = $this->post(route('register'), $this->validParams([
            'password' => 'pass1',
            'password_confirmation' => 'pass1',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertSame('パスワードは8文字以上で入力してください', session('errors')->first('password'));
    }

    public function test_password_confirmation_must_match(): void
    {
        $response = $this->post(route('register'), $this->validParams([
            'password' => 'password',
            'password_confirmation' => 'different',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertSame('パスワードと一致しません', session('errors')->first('password'));
    }

    public function test_password_is_required(): void
    {
        $response = $this->post(route('register'), $this->validParams([
            'password' => '',
            'password_confirmation' => '',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertSame('パスワードを入力してください', session('errors')->first('password'));
    }

    public function test_valid_input_creates_a_user_and_logs_in(): void
    {
        $this->post(route('register'), $this->validParams());

        $this->assertDatabaseHas('users', [
            'email' => 'taro@example.com',
            'name' => 'テスト太郎',
        ]);
        $this->assertAuthenticated();
    }
}
