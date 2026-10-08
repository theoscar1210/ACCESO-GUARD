<?php

namespace Tests\Feature\Works;

use App\Models\Property;
use App\Models\Work;
use App\Support\WorkPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class WorkManagementTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function payload(int $propertyId, array $overrides = []): array
    {
        return [
            'property_id' => $propertyId,
            'title' => 'Remodelación cocina',
            'type' => 'locativa',
            'contractor_name' => 'Pedro Ruiz',
            'start_date' => today()->toDateString(),
            'workers' => [
                ['first_name' => 'Juan', 'last_name' => 'Pérez', 'cedula' => '9001'],
                ['first_name' => 'Luis', 'last_name' => 'Gómez', 'cedula' => '9002'],
            ],
            ...$overrides,
        ];
    }

    private function work(Property $property, string $status = 'aprobada'): Work
    {
        $work = Work::create([
            'property_id' => $property->id, 'title' => 'Obra', 'type' => 'locativa',
            'contractor_name' => 'Contratista', 'start_date' => today(), 'status' => $status,
            'created_by' => $this->admin()->id,
        ]);
        $work->workers()->create(['first_name' => 'Juan', 'last_name' => 'Pérez', 'cedula' => '9001']);

        return $work;
    }

    public function test_propietario_work_is_pending_and_only_for_own_property(): void
    {
        $owner = $this->propietarioWithProperty('101');
        $own = Property::where('number', '101')->first();
        $other = Property::create(['number' => '202', 'type' => 'apartamento']);

        $this->actingAs($owner)->post('/works', $this->payload($other->id))->assertSessionHasErrors('property_id');

        $this->actingAs($owner)->post('/works', $this->payload($own->id))->assertSessionHasNoErrors();

        $work = Work::firstOrFail();
        $this->assertSame('pendiente', $work->status);
        $this->assertCount(2, $work->workers);
        $this->assertDatabaseHas('work_logs', ['work_id' => $work->id, 'action' => 'creada', 'user_id' => $owner->id]);
    }

    public function test_vigilante_and_admin_works_are_approved_on_creation(): void
    {
        $property = Property::create(['number' => '303', 'type' => 'casa']);

        $this->actingAs($this->vigilante())->post('/works', $this->payload($property->id))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post('/works', $this->payload($property->id, ['title' => 'Otra']))->assertSessionHasNoErrors();

        $this->assertSame(['aprobada', 'aprobada'], Work::pluck('status')->all());
    }

    public function test_workers_need_unique_cedulas(): void
    {
        $property = Property::create(['number' => '303', 'type' => 'casa']);

        $this->actingAs($this->admin())
            ->post('/works', $this->payload($property->id, ['workers' => [
                ['first_name' => 'A', 'last_name' => 'B', 'cedula' => '1'],
                ['first_name' => 'C', 'last_name' => 'D', 'cedula' => '1'],
            ]]))
            ->assertSessionHasErrors('workers.1.cedula');
    }

    public function test_superuser_settings_control_who_creates_and_approval(): void
    {
        $super = $this->userWithRole('Superusuario');
        $property = Property::create(['number' => '303', 'type' => 'casa']);

        $roles = WorkPermissions::DEFAULTS;
        $roles['Vigilante'] = ['can_create' => false, 'requires_approval' => false];
        $roles['Propietario'] = ['can_create' => true, 'requires_approval' => false];

        $this->actingAs($super)->put('/admin/settings/works', ['roles' => $roles])->assertSessionHasNoErrors();

        $this->actingAs($this->vigilante())->get('/works/create')->assertForbidden();
        $this->actingAs($this->vigilante())->post('/works', $this->payload($property->id))->assertForbidden();

        $owner = $this->propietarioWithProperty('404');
        $this->actingAs($owner)->post('/works', $this->payload(Property::where('number', '404')->value('id')));
        $this->assertSame('aprobada', Work::latest('id')->first()->status);
    }

    public function test_admin_decides_and_every_decision_is_logged(): void
    {
        $admin = $this->admin();
        $work = $this->work(Property::create(['number' => '505', 'type' => 'casa']), 'pendiente');

        $this->actingAs($admin)->post("/works/{$work->id}/decision", ['action' => 'aprobar'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/works/{$work->id}/decision", ['action' => 'suspender', 'notes' => 'Ruido fuera de horario']);
        $this->actingAs($admin)->post("/works/{$work->id}/decision", ['action' => 'cerrar']);
        $this->actingAs($admin)->post("/works/{$work->id}/decision", ['action' => 'reabrir']);

        $work->refresh();
        $this->assertSame('aprobada', $work->status);
        $this->assertSame($admin->id, $work->decided_by);
        $this->assertSame(['aprobada', 'suspendida', 'cerrada', 'reabierta'], $work->logs()->oldest('id')->pluck('action')->all());
        $this->assertDatabaseHas('work_logs', ['action' => 'suspendida', 'notes' => 'Ruido fuera de horario']);

        $this->actingAs($admin)->post("/works/{$work->id}/decision", ['action' => 'aprobar'])->assertSessionHasErrors('action');
    }

    public function test_only_admin_or_superuser_can_decide_or_edit(): void
    {
        $owner = $this->propietarioWithProperty('606');
        $work = $this->work(Property::where('number', '606')->first(), 'pendiente');

        $this->actingAs($owner)->post("/works/{$work->id}/decision", ['action' => 'aprobar'])->assertForbidden();
        $this->actingAs($this->vigilante())->post("/works/{$work->id}/decision", ['action' => 'aprobar'])->assertForbidden();
        $this->actingAs($owner)->put("/works/{$work->id}", ['title' => 'x'])->assertForbidden();

        $this->actingAs($this->userWithRole('Superusuario'))
            ->post("/works/{$work->id}/decision", ['action' => 'rechazar'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rechazada', $work->fresh()->status);
    }

    public function test_owners_only_see_works_of_their_property(): void
    {
        $owner = $this->propietarioWithProperty('707');
        $mine = $this->work(Property::where('number', '707')->first());
        $foreign = $this->work(Property::create(['number' => '808', 'type' => 'casa']));

        $this->actingAs($owner)->get('/works')
            ->assertInertia(fn (Assert $page) => $page->has('works', 1)->where('works.0.id', $mine->id));
        $this->actingAs($owner)->get("/works/{$foreign->id}")->assertForbidden();
        $this->actingAs($owner)->get("/works/{$mine->id}")->assertOk();

        $this->actingAs($this->vigilante())->get('/works')->assertInertia(fn (Assert $page) => $page->has('works', 2));
    }

    public function test_workers_can_be_added_and_removed(): void
    {
        $work = $this->work(Property::create(['number' => '909', 'type' => 'casa']));
        $guard = $this->vigilante();

        $this->actingAs($guard)
            ->post("/works/{$work->id}/workers", ['first_name' => 'Ana', 'last_name' => 'Ríos', 'cedula' => '9100'])
            ->assertSessionHasNoErrors();
        $this->actingAs($guard)
            ->post("/works/{$work->id}/workers", ['first_name' => 'Ana', 'last_name' => 'Ríos', 'cedula' => '9100'])
            ->assertSessionHasErrors('cedula');

        $worker = $work->workers()->where('cedula', '9100')->first();
        $this->actingAs($guard)->delete("/works/{$work->id}/workers/{$worker->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($worker);
    }
}
