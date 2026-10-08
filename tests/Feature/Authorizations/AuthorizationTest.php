<?php

namespace Tests\Feature\Authorizations;

use App\Models\Property;
use App\Models\PropertyRental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

/**
 * Autorizaciones de visitantes: el flujo es idéntico para Propietario y Residente.
 */
class AuthorizationTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public static function roles(): array
    {
        return [
            'propietario' => ['Propietario', 'propietario'],
            'residente' => ['Residente', 'residente'],
        ];
    }

    private function payload(array $overrides = []): array
    {
        return [
            'first_name' => 'Laura',
            'last_name' => 'Díaz',
            'cedula' => '87654321',
            'type' => 'visitante',
            'start_date' => today()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'observations' => 'Visita familiar',
            ...$overrides,
        ];
    }

    #[DataProvider('roles')]
    public function test_user_can_create_authorization(string $role, string $prefix): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)
            ->post("/{$prefix}/authorizations", $this->payload())
            ->assertRedirect(route("{$prefix}.authorizations.index"));

        $this->assertDatabaseHas('authorizations', [
            'user_id' => $user->id,
            'cedula' => '87654321',
            'status' => 'activo',
        ]);
    }

    #[DataProvider('roles')]
    public function test_user_can_authorize_with_vehicle_plate(string $role, string $prefix): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)
            ->post("/{$prefix}/authorizations", $this->payload(['plate' => ' abc-123 ']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('authorizations', ['user_id' => $user->id, 'plate' => 'ABC-123']);

        $this->actingAs($user)->get("/{$prefix}/authorizations")
            ->assertInertia(fn (Assert $page) => $page->where('authorizations.0.plate', 'ABC-123'));
    }

    #[DataProvider('roles')]
    public function test_plate_is_optional_and_limited(string $role, string $prefix): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->post("/{$prefix}/authorizations", $this->payload(['plate' => '']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('authorizations', ['user_id' => $user->id, 'plate' => null]);

        $this->actingAs($user)->post("/{$prefix}/authorizations", $this->payload(['plate' => str_repeat('A', 21)]))
            ->assertSessionHasErrors('plate');
    }

    public function test_vigilante_sees_property_and_plate_of_authorization(): void
    {
        $owner = $this->propietarioWithProperty('101');
        $this->makeAuthorization($owner, ['plate' => 'XYZ789']);

        $tenant = $this->residente();
        $rented = Property::create(['number' => '12', 'block' => 'B', 'type' => 'casa']);
        PropertyRental::create(['property_id' => $rented->id, 'user_id' => $tenant->id, 'start_date' => today(), 'is_active' => true]);
        $this->makeAuthorization($tenant, ['end_date' => now()->addDays(3)]);

        $this->actingAs($this->vigilante())->get('/vigilante/authorizations')
            ->assertInertia(fn (Assert $page) => $page
                ->has('authorizations', 2)
                ->where('authorizations.0.property', '101')
                ->where('authorizations.0.plate', 'XYZ789')
                ->where('authorizations.1.property', 'B - 12')
                ->where('authorizations.1.plate', null));
    }

    #[DataProvider('roles')]
    public function test_authorization_validation(string $role, string $prefix): void
    {
        $this->actingAs($this->userWithRole($role))
            ->post("/{$prefix}/authorizations", $this->payload([
                'type' => 'propietario',
                'start_date' => today()->subDay()->toDateString(),
                'end_date' => today()->subDays(2)->toDateString(),
            ]))
            ->assertSessionHasErrors(['type', 'start_date', 'end_date']);
    }

    #[DataProvider('roles')]
    public function test_status_and_owner_cannot_be_forged(string $role, string $prefix): void
    {
        $user = $this->userWithRole($role);
        $victim = $this->propietario();

        $this->actingAs($user)->post("/{$prefix}/authorizations", $this->payload([
            'status' => 'usado',
            'user_id' => $victim->id,
        ]));

        $this->assertDatabaseHas('authorizations', ['user_id' => $user->id, 'status' => 'activo']);
        $this->assertDatabaseMissing('authorizations', ['user_id' => $victim->id]);
    }

    #[DataProvider('roles')]
    public function test_index_lists_only_own_authorizations(string $role, string $prefix): void
    {
        $user = $this->userWithRole($role);
        $this->makeAuthorization($user);
        $this->makeAuthorization($this->propietario());

        $this->actingAs($user)->get("/{$prefix}/authorizations")
            ->assertInertia(fn (Assert $page) => $page->has('authorizations', 1));
    }

    #[DataProvider('roles')]
    public function test_user_can_delete_own_authorization(string $role, string $prefix): void
    {
        $user = $this->userWithRole($role);
        $auth = $this->makeAuthorization($user);

        $this->actingAs($user)->delete("/{$prefix}/authorizations/{$auth->id}")->assertRedirect();

        $this->assertModelMissing($auth);
    }

    #[DataProvider('roles')]
    public function test_user_cannot_delete_someone_elses_authorization_idor(string $role, string $prefix): void
    {
        $user = $this->userWithRole($role);
        $other = $this->makeAuthorization($this->propietario());

        $this->actingAs($user)->delete("/{$prefix}/authorizations/{$other->id}")->assertForbidden();

        $this->assertModelExists($other);
    }
}
