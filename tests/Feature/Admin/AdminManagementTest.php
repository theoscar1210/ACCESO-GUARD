<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->admin();
    }

    private function userPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'María',
            'last_name' => 'López',
            'cedula' => '11223344',
            'phone' => '04141234567',
            'username' => 'mlopez',
            'email' => 'mlopez@example.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'role' => 'Vigilante',
            ...$overrides,
        ];
    }

    // ── Usuarios ────────────────────────────────────────────────────────────

    public function test_admin_can_create_user_with_role_and_hashed_password(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', $this->userPayload())
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'mlopez')->firstOrFail();
        $this->assertTrue($user->hasRole('Vigilante'));
        $this->assertNotSame('secreto123', $user->password);
        $this->assertTrue(Hash::check('secreto123', $user->password));
    }

    public function test_user_creation_validates_uniqueness_and_role(): void
    {
        $existing = $this->vigilante();

        $this->actingAs($this->admin)
            ->post('/admin/users', $this->userPayload([
                'cedula' => $existing->cedula,
                'username' => $existing->username,
                'email' => 'no-es-email',
                'password' => 'corta',
                'role' => 'SuperAdmin',
            ]))
            ->assertSessionHasErrors(['cedula', 'username', 'email', 'password', 'role']);
    }

    public function test_admin_can_update_user_and_change_role(): void
    {
        $user = $this->vigilante();

        $this->actingAs($this->admin)
            ->put("/admin/users/{$user->id}", $this->userPayload(['role' => 'Propietario', 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('mlopez', $user->username);
        $this->assertTrue($user->hasRole('Propietario'));
        $this->assertFalse($user->hasRole('Vigilante'));
    }

    public function test_admin_can_delete_user_but_not_self(): void
    {
        $user = $this->vigilante();

        $this->actingAs($this->admin)->delete("/admin/users/{$user->id}")->assertRedirect();
        $this->assertModelMissing($user);

        $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}")->assertSessionHasErrors('error');
        $this->assertModelExists($this->admin);
    }

    public function test_users_index_does_not_expose_password_hashes(): void
    {
        $this->actingAs($this->admin)->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Users/Index')
                ->has('users', 1)
                ->missing('users.0.password')
                ->missing('users.0.two_factor_secret'));
    }

    // ── Inmuebles ───────────────────────────────────────────────────────────

    public function test_admin_can_manage_properties(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/properties', ['number' => '201', 'block' => 'B', 'type' => 'apartamento'])
            ->assertRedirect(route('admin.properties.index'));

        $property = Property::where('number', '201')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/properties', ['number' => '201', 'type' => 'apartamento'])
            ->assertSessionHasErrors('number');

        $this->actingAs($this->admin)
            ->put("/admin/properties/{$property->id}", ['number' => '202', 'type' => 'casa'])
            ->assertSessionHasNoErrors();
        $this->assertSame('casa', $property->fresh()->type);

        $this->actingAs($this->admin)->get("/admin/properties/{$property->id}")->assertOk();

        $this->actingAs($this->admin)->delete("/admin/properties/{$property->id}")->assertRedirect();
        $this->assertModelMissing($property);
    }

    public function test_assign_and_remove_owner(): void
    {
        $property = Property::create(['number' => '301', 'type' => 'apartamento']);
        $owner = $this->propietario();

        $this->actingAs($this->admin)
            ->post("/admin/properties/{$property->id}/assign-owner", ['user_id' => $owner->id, 'since_date' => '2025-01-01']);
        $this->assertTrue($property->owners()->whereKey($owner->id)->exists());
        $this->assertSame('301', $owner->fresh()->property_number);

        $this->actingAs($this->admin)->delete("/admin/properties/{$property->id}/owners/{$owner->id}");
        $this->assertFalse($property->owners()->whereKey($owner->id)->exists());
    }

    public function test_only_propietarios_can_be_assigned_as_owner(): void
    {
        $property = Property::create(['number' => '302', 'type' => 'apartamento']);
        $vig = $this->vigilante();

        $this->actingAs($this->admin)
            ->post("/admin/properties/{$property->id}/assign-owner", ['user_id' => $vig->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_assign_tenant_closes_previous_rental_and_end_rental(): void
    {
        $property = Property::create(['number' => '401', 'type' => 'apartamento']);
        $first = $this->residente();
        $second = $this->residente();

        $this->actingAs($this->admin)->post("/admin/properties/{$property->id}/assign-tenant", ['user_id' => $first->id, 'start_date' => '2026-01-01']);
        $this->actingAs($this->admin)->post("/admin/properties/{$property->id}/assign-tenant", ['user_id' => $second->id, 'start_date' => '2026-02-01']);

        $this->assertSame(1, $property->rentals()->where('is_active', true)->count());
        $this->assertSame($second->id, $property->fresh()->activeRental->user_id);
        $this->assertSame('401', $second->fresh()->property_number);
        $this->assertNull($first->fresh()->property_number);

        $this->actingAs($this->admin)->post("/admin/properties/{$property->id}/end-rental");
        $this->assertSame(0, $property->rentals()->where('is_active', true)->count());
    }

    public function test_assign_tenant_validates_dates(): void
    {
        $property = Property::create(['number' => '402', 'type' => 'apartamento']);

        $this->actingAs($this->admin)
            ->post("/admin/properties/{$property->id}/assign-tenant", [
                'user_id' => $this->residente()->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-04-01',
            ])
            ->assertSessionHasErrors('end_date');
    }

    // ── Núcleo familiar ─────────────────────────────────────────────────────

    public function test_admin_can_manage_family_members(): void
    {
        $owner = $this->propietario();
        $data = ['user_id' => $owner->id, 'first_name' => 'Luis', 'last_name' => 'P', 'cedula' => '3030', 'relationship' => 'hijo'];

        $this->actingAs($this->admin)->post('/admin/family-members', $data)->assertSessionHasNoErrors();
        $member = FamilyMember::where('cedula', '3030')->firstOrFail();

        $this->actingAs($this->admin)->post('/admin/family-members', $data)->assertSessionHasErrors('cedula');
        $this->actingAs($this->admin)->post('/admin/family-members', [...$data, 'cedula' => '4040', 'relationship' => 'vecino'])
            ->assertSessionHasErrors('relationship');

        $this->actingAs($this->admin)
            ->put("/admin/family-members/{$member->id}", [...$data, 'first_name' => 'Luisito'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Luisito', $member->fresh()->first_name);

        $this->actingAs($this->admin)->delete("/admin/family-members/{$member->id}");
        $this->assertModelMissing($member);
    }

    // ── Comunicados ─────────────────────────────────────────────────────────

    public function test_announcement_is_sent_only_to_target_role(): void
    {
        Notification::fake();
        $owner = $this->propietario();
        $tenant = $this->residente();
        $guard = $this->vigilante();

        $this->actingAs($this->admin)
            ->post('/admin/announcements', ['title' => 'Corte de agua', 'body' => 'Mañana 8am', 'target' => 'propietario', 'send_push' => false])
            ->assertRedirect(route('admin.announcements.index'));

        Notification::assertSentTo($owner, AnnouncementNotification::class);
        Notification::assertNotSentTo([$tenant, $guard, $this->admin], AnnouncementNotification::class);
    }

    public function test_announcements_visible_by_target_and_can_be_marked_read(): void
    {
        $owner = $this->propietario();
        $tenant = $this->residente();
        $forOwners = Announcement::create(['created_by' => $this->admin->id, 'title' => 'P', 'body' => 'x', 'target' => 'propietario', 'send_push' => false]);
        Announcement::create(['created_by' => $this->admin->id, 'title' => 'All', 'body' => 'x', 'target' => 'all', 'send_push' => false]);

        $this->actingAs($owner)->get('/announcements')->assertInertia(fn (Assert $p) => $p->has('announcements', 2));
        $this->actingAs($tenant)->get('/announcements')->assertInertia(fn (Assert $p) => $p->has('announcements', 1));

        $this->actingAs($owner)->post("/announcements/{$forOwners->id}/read")->assertRedirect();
        $this->assertDatabaseHas('announcement_user', ['announcement_id' => $forOwners->id, 'user_id' => $owner->id]);
    }

    public function test_announcement_validation(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/announcements', ['title' => '', 'body' => '', 'target' => 'vigilante'])
            ->assertSessionHasErrors(['title', 'body', 'target']);
    }
}
