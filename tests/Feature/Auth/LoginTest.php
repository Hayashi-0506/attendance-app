<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID2: ログイン認証機能（一般ユーザー）
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_required(): void
    {
        $response = $this->post(route('login'), ['password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertSame('メールアドレスを入力してください', session('errors')->first('email'));
    }

    public function test_password_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), ['email' => $user->email]);

        $response->assertSessionHasErrors('password');
        $this->assertSame('パスワードを入力してください', session('errors')->first('password'));
    }

    public function test_login_fails_with_unregistered_credentials(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'notfound@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('ログイン情報が登録されていません', session('errors')->first('email'));
    }

    public function test_valid_credentials_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
