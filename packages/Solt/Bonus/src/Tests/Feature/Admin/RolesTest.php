<?php

namespace Solt\Bonus\Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Solt\Bonus\Models\LegalEntity;
use Solt\Bonus\Models\Pharmacy;
use Tests\TestCase;

/**
 * Роли, первый вход с созданием пароля, управление пользователями.
 */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['login' => 'admin', 'name' => 'Админ', 'role' => User::ROLE_ADMIN, 'password' => 'Admin-123']);
    }

    public function test_first_login_sets_password_and_validates_it()
    {
        // в тесте много попыток подряд — лимит попыток входа отключаем
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->artisan('bonus:admin', ['login' => 'boss'])->assertSuccessful();
        $boss = User::where('login', 'boss')->firstOrFail();
        $this->assertTrue($boss->needsPassword());
        $this->assertSame(User::ROLE_ADMIN, $boss->role);

        // вход только по логину → страница создания пароля
        $this->post('/admin/login', ['login' => 'boss'])->assertRedirect('/admin/password/setup');
        $this->get('/admin/password/setup')->assertOk()->assertSee('Первый вход');

        // плохие пароли
        foreach (['short1', 'onlyletters', '12345678', 'пароль123', 'has space1', str_repeat('a1', 40)] as $bad) {
            $this->post('/admin/password/setup', ['password' => $bad, 'password_confirmation' => $bad])
                ->assertSessionHasErrors('password');
        }
        $this->post('/admin/password/setup', ['password' => 'Good-pass1', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors('password');

        // хороший — сохраняется, пользователь залогинен
        $this->post('/admin/password/setup', ['password' => 'Good-pass1', 'password_confirmation' => 'Good-pass1'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($boss->fresh());
        $this->assertFalse($boss->fresh()->needsPassword());

        // повторно страница недоступна, вход — уже по паролю
        $this->post('/admin/logout');
        $this->get('/admin/password/setup')->assertRedirect('/admin/login');
        $this->post('/admin/login', ['login' => 'boss'])->assertSessionHas('error');
        $this->post('/admin/login', ['login' => 'boss', 'password' => 'Good-pass1'])->assertRedirect('/admin');
    }

    public function test_setup_page_requires_login_step()
    {
        $this->get('/admin/password/setup')->assertRedirect('/admin/login');
        $this->post('/admin/password/setup', ['password' => 'Good-pass1', 'password_confirmation' => 'Good-pass1'])
            ->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_user_role_is_read_only()
    {
        $user = User::create(['login' => 'viewer', 'role' => User::ROLE_USER, 'password' => 'Viewer-123']);
        $le = LegalEntity::factory()->create();
        $ph = Pharmacy::factory()->create(['legal_entity_id' => $le->id]);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/pharmacies')->assertOk()->assertDontSee('Добавить аптеку');
        $this->actingAs($user)->get('/admin/legal-entities')->assertOk()->assertDontSee('Добавить юрлицо');
        $this->actingAs($user)->get('/admin/reports')->assertOk();

        $this->actingAs($user)->get('/admin/import')->assertForbidden();
        $this->actingAs($user)->post('/admin/pharmacies', ['number' => 1, 'name' => 'x', 'legal_entity_id' => $le->id])->assertForbidden();
        $this->actingAs($user)->delete("/admin/pharmacies/{$ph->id}")->assertForbidden();
        $this->actingAs($user)->post('/admin/legal-entities', ['inn' => '5410125764', 'name' => 'x'])->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->assertNull($ph->fresh()->deleted_at);
    }

    public function test_editor_edits_data_but_not_users()
    {
        $editor = User::create(['login' => 'editor', 'role' => User::ROLE_EDITOR, 'password' => 'Editor-123']);
        $le = LegalEntity::factory()->create();

        $this->actingAs($editor)->get('/admin/import')->assertOk();
        $this->actingAs($editor)->post('/admin/pharmacies', ['number' => 3, 'name' => 'Новая', 'legal_entity_id' => $le->id])
            ->assertSessionHas('success');
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($editor)->post('/admin/users', ['login' => 'x1', 'role' => 'admin'])->assertForbidden();
    }

    public function test_admin_manages_users()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Новый пользователь');

        $this->actingAs($admin)->post('/admin/users', ['login' => 'ivanov', 'name' => 'Иванов', 'role' => 'editor'])
            ->assertSessionHas('success');
        $u = User::where('login', 'ivanov')->firstOrFail();
        $this->assertTrue($u->needsPassword());
        $this->assertSame('editor', $u->role);

        $this->actingAs($admin)->post('/admin/users', ['login' => 'ivanov', 'role' => 'user'])->assertSessionHasErrors('login');
        $this->actingAs($admin)->post('/admin/users', ['login' => 'плохой', 'role' => 'user'])->assertSessionHasErrors('login');
        $this->actingAs($admin)->post('/admin/users', ['login' => 'ok', 'role' => 'superuser'])->assertSessionHasErrors('role');

        $this->actingAs($admin)->post("/admin/users/{$u->id}/role", ['role' => 'user'])->assertSessionHas('success');
        $this->assertSame('user', $u->fresh()->role);

        // сброс пароля
        $u->password = 'Ivanov-123'; $u->save();
        $this->actingAs($admin)->post("/admin/users/{$u->id}/reset-password")->assertSessionHas('success');
        $this->assertTrue($u->fresh()->needsPassword());

        // себя и последнего админа — нельзя
        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertSessionHasErrors();
        $this->actingAs($admin)->post("/admin/users/{$admin->id}/role", ['role' => 'user'])->assertSessionHasErrors();
        $this->assertSame('admin', $admin->fresh()->role);

        $this->actingAs($admin)->delete("/admin/users/{$u->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }

    public function test_change_own_password()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/password')->assertOk();
        $this->actingAs($admin)->post('/admin/password', [
            'current_password' => 'wrong', 'password' => 'New-pass-1', 'password_confirmation' => 'New-pass-1',
        ])->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->post('/admin/password', [
            'current_password' => 'Admin-123', 'password' => 'New-pass-1', 'password_confirmation' => 'New-pass-1',
        ])->assertRedirect('/admin');

        $this->post('/admin/logout');
        $this->post('/admin/login', ['login' => 'admin', 'password' => 'New-pass-1'])->assertRedirect('/admin');
    }
}
