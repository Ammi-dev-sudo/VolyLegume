<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_an_existing_admin_password_when_no_password_is_configured(): void
    {
        config([
            'services.platform_admin.email' => 'admin@example.test',
            'services.platform_admin.name' => 'Admin test',
            'services.platform_admin.password' => '',
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'original-secret',
            'is_platform_admin' => false,
            'is_active' => false,
        ]);

        $this->artisan('app:create-admin-user')->assertSuccessful();

        $admin->refresh();
        $this->assertTrue(Hash::check('original-secret', $admin->password));
        $this->assertTrue($admin->is_platform_admin);
        $this->assertTrue($admin->is_active);
    }

    public function test_it_refuses_to_create_an_admin_without_a_configured_password(): void
    {
        config([
            'services.platform_admin.email' => 'admin@example.test',
            'services.platform_admin.name' => 'Admin test',
            'services.platform_admin.password' => '',
        ]);

        $this->artisan('app:create-admin-user')->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public function test_it_creates_an_admin_with_the_configured_password(): void
    {
        config([
            'services.platform_admin.email' => 'admin@example.test',
            'services.platform_admin.name' => 'Admin test',
            'services.platform_admin.password' => 'a-long-test-password',
        ]);

        $this->artisan('app:create-admin-user')->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('a-long-test-password', $admin->password));
        $this->assertTrue($admin->is_platform_admin);
        $this->assertTrue($admin->is_active);
    }
}
