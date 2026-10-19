<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_route_can_be_accessed(): void
    {
        $response = $this->get('/migration');

        $response->assertOk();
        $response->assertSee('Migration Successful');
        $response->assertSee('Migration Output');
    }

    public function test_migrate_alias_works(): void
    {
        $response = $this->get('/migrate');

        $response->assertOk();
        $response->assertSee('Migration Successful');
    }

    public function test_migration_key_protection_when_configured(): void
    {
        putenv('MIGRATION_KEY=superSecret123');

        // Without key
        $unauth = $this->get('/migration');
        $unauth->assertStatus(403);
        $unauth->assertSee('Unauthorized Access');

        // With wrong key
        $wrong = $this->get('/migration?key=wrong');
        $wrong->assertStatus(403);

        // With correct key
        $auth = $this->get('/migration?key=superSecret123');
        $auth->assertOk();
        $auth->assertSee('Migration Successful');

        putenv('MIGRATION_KEY');
    }
}
