<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * テストケース一覧 ID16: ★ メール認証機能(1)
 * 「会員登録後、認証メールが送信される」
 */
class EmailVerificationNotificationTest extends TestCase
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

    public function test_registering_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register'), $this->validParams());

        $user = User::where('email', 'taro@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_email_is_sent_to_the_registered_address(): void
    {
        Notification::fake();

        $this->post(route('register'), $this->validParams(['email' => 'hanako@example.com']));

        $hanako = User::where('email', 'hanako@example.com')->first();
        $this->assertNotNull($hanako);

        Notification::assertSentTo($hanako, VerifyEmail::class);
        // 他のユーザーには送信されていないことも確認
        Notification::assertCount(1);
    }

    public function test_newly_registered_user_is_not_verified_yet(): void
    {
        $this->post(route('register'), $this->validParams());

        $user = User::where('email', 'taro@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
    }
}
