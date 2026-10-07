<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

/**
 * Control de acceso por rol: cada panel solo debe ser accesible por su rol.
 */
class RoleAccessTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    private const ROLES = ['Administrador', 'Vigilante', 'Propietario', 'Residente'];

    private const PANEL_ROUTES = [
        'Administrador' => ['/admin/dashboard', '/admin/users', '/admin/users/create', '/admin/entries', '/admin/properties', '/admin/announcements'],
        'Vigilante' => ['/vigilante/dashboard', '/vigilante/entries', '/vigilante/entries/create', '/vigilante/exits', '/vigilante/authorizations', '/vigilante/reports'],
        'Propietario' => ['/propietario/dashboard', '/propietario/authorizations', '/propietario/authorizations/create', '/propietario/history'],
        'Residente' => ['/residente/dashboard', '/residente/authorizations', '/residente/authorizations/create', '/residente/history'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public static function panelRoutes(): array
    {
        $cases = [];
        foreach (self::PANEL_ROUTES as $role => $uris) {
            foreach ($uris as $uri) {
                $cases["{$role} {$uri}"] = [$role, $uri];
            }
        }

        return $cases;
    }

    #[DataProvider('panelRoutes')]
    public function test_guest_is_redirected_to_login(string $role, string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    #[DataProvider('panelRoutes')]
    public function test_owner_role_can_access_its_panel(string $role, string $uri): void
    {
        $this->actingAs($this->userWithRole($role))->get($uri)->assertOk();
    }

    #[DataProvider('panelRoutes')]
    public function test_other_roles_are_forbidden(string $role, string $uri): void
    {
        foreach (array_diff(self::ROLES, [$role]) as $other) {
            $this->actingAs($this->userWithRole($other))
                ->get($uri)
                ->assertForbidden();
        }
    }

    public function test_user_without_role_cannot_access_any_panel(): void
    {
        $user = \App\Models\User::factory()->create();

        foreach (self::PANEL_ROUTES as $uris) {
            $this->actingAs($user)->get($uris[0])->assertForbidden();
        }
    }

    public function test_non_admin_cannot_create_users_or_escalate_privileges(): void
    {
        foreach (['Vigilante', 'Propietario', 'Residente'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post('/admin/users', [
                    'first_name' => 'Hacker', 'last_name' => 'X', 'cedula' => '999'.$role,
                    'username' => 'hacker'.$role, 'email' => "h{$role}@x.com",
                    'password' => 'password123', 'password_confirmation' => 'password123',
                    'role' => 'Administrador',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['username' => 'hackerVigilante']);
    }

    public function test_dashboard_redirects_each_role_to_its_panel(): void
    {
        $map = [
            'Administrador' => 'admin.dashboard',
            'Vigilante' => 'vigilante.dashboard',
            'Propietario' => 'propietario.dashboard',
            'Residente' => 'residente.dashboard',
        ];

        foreach ($map as $role => $route) {
            $this->actingAs($this->userWithRole($role))
                ->get('/dashboard')
                ->assertRedirect(route($route));
        }
    }
}
