<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName = 'Super Admin', array $permissions = []): User
    {
        $role = Role::create(['name' => $roleName, 'permissions' => $permissions, 'is_active' => true]);
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    public function test_customer_documents_require_an_untampered_signature(): void
    {
        foreach (['/customer/1/history', '/customer/1/bill/1', '/customer/1/pay/1'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $url = URL::signedRoute('customer.history.view', ['customer' => 987654]);
        $this->get($url)->assertNotFound();
        $this->get(str_replace('987654', '987655', $url))->assertForbidden();
    }

    public function test_runtime_endpoint_requires_super_admin_and_reports_actual_process(): void
    {
        $this->get('/settings/security-runtime')->assertRedirect(route('login'));
        $user = $this->user('Cashier');
        $this->actingAs($user)->get('/settings/security-runtime')->assertRedirect(route('dashboard'));
        $user->role->update(['name' => 'Super Admin']);
        $this->getJson('/settings/security-runtime')->assertOk()
            ->assertJsonPath('php_version', PHP_VERSION)->assertJsonPath('sapi', PHP_SAPI)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_login_is_throttled_and_rejects_array_credentials(): void
    {
        $this->postJson('/login', ['username' => 'invalid', 'password' => ['bad']])->assertUnprocessable();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/login', ['username' => 'invalid', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->postJson('/login', ['username' => 'invalid', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_disabled_user_session_is_revoked(): void
    {
        $user = $this->user();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_disabled_role_session_is_revoked(): void
    {
        $user = $this->user();
        $user->role->update(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
    }

    public function test_public_pin_does_not_authorize_secret_admin_functions(): void
    {
        $this->withSession(['secretpos.auth' => true])->get('/information/secret')->assertRedirect(route('login'));
        $this->actingAs($this->user('Cashier'))->withSession(['secretpos.auth' => true])
            ->post('/information/secret/save', ['code' => '1234'])->assertRedirect(route('dashboard'));
    }

    public function test_unconfigured_secret_pin_fails_closed(): void
    {
        $this->actingAs($this->user())->post('/information/login', ['code' => '0000'])
            ->assertSessionHasErrors('code')->assertSessionMissing('secretpos.auth');
    }

    public function test_role_editor_cannot_grant_permissions_they_do_not_have(): void
    {
        $this->actingAs($this->user('Role Editor', ['roles.create']))
            ->post('/roles', ['name' => 'Elevated', 'permissions' => ['users.edit'], 'is_active' => true])
            ->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Elevated']);
    }

    public function test_user_editor_cannot_assign_administrator_role(): void
    {
        $actor = $this->user('User Editor', ['users.create']);
        $admin = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs($actor)->post('/users', [
            'name' => 'Elevated', 'username' => 'elevated', 'email' => 'elevated@example.test',
            'password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password',
            'role_id' => $admin->id, 'is_active' => true,
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'elevated']);
    }

    public function test_pos_access_does_not_allow_editing_existing_sales_or_returns(): void
    {
        $this->actingAs($this->user('POS Only', ['pos.access']));
        $this->postJson('/pos/checkout', ['edit_sale_id' => 1])->assertForbidden();
        $this->postJson('/pos/cart/add-return', ['sale_item_id' => 1, 'quantity' => 1])->assertForbidden();
        $this->postJson('/pos/search-sale', ['term' => '1'])->assertForbidden();
    }

    public function test_maintenance_post_requires_csrf_outside_the_test_bypass(): void
    {
        $this->actingAs($this->user());
        $this->app['env'] = 'production';
        $this->post('/migration')->assertStatus(419);
        $this->post('/run-seed')->assertStatus(419);
    }

    public function test_demo_seeder_refuses_production_without_changing_accounts(): void
    {
        $this->app['env'] = 'production';
        try {
            (new \Database\Seeders\DatabaseSeeder)->run();
            $this->fail('Production demo seed must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Demo seeding is disabled', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }
}
