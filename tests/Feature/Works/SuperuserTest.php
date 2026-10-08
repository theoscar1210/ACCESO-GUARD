<?php

namespace Tests\Feature\Works;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class SuperuserTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_superuser_lands_on_admin_panel_and_can_use_every_area(): void
    {
        $super = $this->userWithRole('Superusuario', ['username' => 'root']);

        $this->post('/login', ['username' => 'root', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        foreach (['/admin/dashboard', '/admin/users', '/vigilante/entries/create', '/vigilante/exits', '/works', '/admin/settings/works'] as $uri) {
            $this->actingAs($super)->get($uri)->assertOk();
        }
    }

    public function test_only_superuser_can_open_work_settings(): void
    {
        $this->actingAs($this->admin())->get('/admin/settings/works')->assertForbidden();
        $this->actingAs($this->vigilante())->get('/admin/settings/works')->assertForbidden();
    }

    public function test_admin_cannot_create_or_assign_superuser_role(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'first_name' => 'X', 'last_name' => 'Y', 'cedula' => '777', 'username' => 'trepa',
                'email' => 'trepa@x.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
                'role' => 'Superusuario',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'trepa']);
    }

    public function test_admin_cannot_edit_or_delete_a_superuser(): void
    {
        $super = $this->userWithRole('Superusuario');
        $admin = $this->admin();

        $this->actingAs($admin)->get("/admin/users/{$super->id}/edit")->assertForbidden();
        $this->actingAs($admin)->delete("/admin/users/{$super->id}")->assertForbidden();
        $this->assertModelExists($super);
    }

    public function test_superuser_can_create_admins_and_superusers(): void
    {
        $this->actingAs($this->userWithRole('Superusuario'))
            ->post('/admin/users', [
                'first_name' => 'Nuevo', 'last_name' => 'Super', 'cedula' => '888', 'username' => 'super2',
                'email' => 'super2@x.com', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
                'role' => 'Superusuario',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('username', 'super2')->first()->hasRole('Superusuario'));
    }
}
