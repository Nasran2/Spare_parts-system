<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MigrationRouteTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::create(['name' => 'Super Admin', 'permissions' => [], 'is_active' => true]);
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    public function test_guest_cannot_run_maintenance_even_with_a_query_key(): void
    {
        Artisan::shouldReceive('call')->never();
        foreach (['migration', 'migrate', 'run-migration', 'run-seed', 'seed'] as $path) {
            $this->get('/'.$path.'?key=anything')->assertRedirect(route('login'));
            $this->post('/'.$path)->assertRedirect(route('login'));
        }
    }

    public function test_get_only_shows_confirmation_without_running_commands(): void
    {
        $this->actingAs($this->superAdmin());
        Artisan::shouldReceive('call')->never();
        foreach (['migration', 'migrate', 'run-migration', 'run-seed', 'seed'] as $path) {
            $this->get('/'.$path)->assertOk()->assertSee('Confirm maintenance')->assertSee('name="_token"', false);
        }
        $this->get('/run-seed?check=1')->assertOk()->assertSee('Confirm maintenance');
    }

    public function test_authorized_post_runs_migration_and_alias(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post('/migration')->assertOk()->assertSee('Migration Successful')->assertSee('Migration Output');
        $this->post('/migrate')->assertOk()->assertSee('Migration Successful');
    }

    public function test_ordinary_administrator_cannot_run_maintenance(): void
    {
        $role = Role::create(['name' => 'Admin', 'permissions' => [], 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
        $this->post('/migration')->assertForbidden();
        $this->post('/run-seed')->assertForbidden();
    }
}
