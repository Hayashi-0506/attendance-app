<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * テストケース一覧 ID16: ★ メール認証機能(2)(3)
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_sees_the_verification_notice_page(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
        $response->assertViewIs('auth.verify-email');
    }

    public function test_verification_notice_page_has_a_link_to_the_mail_verification_site(): void
    {
        // resources/views/auth/verify-email.blade.php の「認証はこちらから」ボタン
        // (href="http://localhost:8025" の静的リンク)を検証
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertSee('認証はこちらから');
        $response->assertSee('http://localhost:8025', false);
    }

    public function test_already_verified_user_is_redirected_away_from_the_notice_page(): void
    {
        // UserFactory のデフォルトで email_verified_at 済み
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertRedirect();
    }

    public function test_guest_cannot_access_the_verification_notice_page(): void
    {
        $response = $this->get(route('verification.notice'));

        $response->assertRedirect(route('login'));
    }

    public function test_visiting_the_signed_verification_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_visiting_the_signed_verification_link_redirects_to_the_attendance_page(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(route('attendance.index').'?verified=1');
    }

    public function test_invalid_hash_does_not_verify_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email@example.com')]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_is_redirected_from_the_attendance_page_to_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_the_attendance_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('attendance.index'));

        $response->assertOk();
    }
}
