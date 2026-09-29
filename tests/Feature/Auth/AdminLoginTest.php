<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID3: ログイン認証機能（管理者）
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_required(): void
    {
        $response = $this->post('/admin/login', ['password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertSame('メールアドレスを入力してください', session('errors')->first('email'));
    }

    public function test_password_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/admin/login', ['email' => $admin->email]);

        $response->assertSessionHasErrors('password');
        $this->assertSame('パスワードを入力してください', session('errors')->first('password'));
    }

    public function test_login_fails_when_credentials_do_not_match(): void
    {
        $admin = User::factory()->admin()->create(['is_admin' => true]);

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('ログイン情報が登録されていません', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_login_fails_for_non_admin_user_even_with_correct_password(): void
    {
        // is_admin が false のユーザーは、パスワードが正しくても管理者ログインできない
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_can_login_with_correct_credentials(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dailyAttendanceList'));
    }
}
