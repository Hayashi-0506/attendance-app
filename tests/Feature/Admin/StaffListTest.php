<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 ID14: ユーザー情報取得機能（管理者） 前半
 */
class StaffListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_all_users_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => '山田太郎', 'email' => 'yamada@example.com']);
        User::factory()->create(['name' => '鈴木花子', 'email' => 'suzuki@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.staffList'));

        $response->assertOk();
        $response->assertSee('山田太郎');
        $response->assertSee('yamada@example.com');
        $response->assertSee('鈴木花子');
        $response->assertSee('suzuki@example.com');
    }

    public function test_non_admin_cannot_access_staff_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.staffList'));

        $response->assertRedirect('/admin/login');
    }
}
