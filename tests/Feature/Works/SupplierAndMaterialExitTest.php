<?php

namespace Tests\Feature\Works;

use App\Models\Entry;
use App\Models\MaterialExit;
use App\Models\Property;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkItem;
use App\Models\WorkWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

/**
 * Entregas de proveedores, salidas de material autorizadas, acta PDF y alertas.
 */
class SupplierAndMaterialExitTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    private User $guard;

    private User $owner;

    private Property $house;

    private Work $work;

    private WorkWorker $juan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');

        $this->guard = $this->vigilante();
        $this->owner = $this->propietarioWithProperty('Casa-02');
        $this->house = Property::where('number', 'Casa-02')->first();
        $this->work = $this->makeWork($this->house);
        $this->juan = $this->work->workers()->create(['first_name' => 'Juan', 'last_name' => 'Pérez', 'cedula' => '9001']);
    }

    private function makeWork(Property $property, string $status = 'aprobada'): Work
    {
        return Work::create([
            'property_id' => $property->id, 'title' => 'Remodelación', 'type' => 'construccion',
            'contractor_name' => 'Constructora Ruiz', 'start_date' => today(), 'status' => $status,
            'created_by' => $this->admin()->id,
        ]);
    }

    private function deliver(array $items, array $overrides = [])
    {
        return $this->actingAs($this->guard)->post('/vigilante/entries', [
            'first_name' => 'Carlos',
            'last_name' => 'Repartidor',
            'cedula' => '7001',
            'apartment' => $this->house->number,
            'type' => 'proveedor',
            'vehicle' => 'camioneta',
            'plate' => 'FER123',
            'work_id' => $this->work->id,
            'supplier_company' => 'Ferretería El Tornillo',
            'items' => $items,
            ...$overrides,
        ]);
    }

    private function exitOf(string $cedula, array $extra = [])
    {
        $entry = Entry::where('cedula', $cedula)->active()->firstOrFail();

        return [$entry, $this->actingAs($this->guard)->post('/vigilante/exits', ['entry_ids' => [$entry->id], ...$extra])];
    }

    // ── Proveedores ─────────────────────────────────────────────────────────

    public function test_supplier_delivers_material_to_the_house(): void
    {
        $this->deliver([
            ['name' => 'Cemento 50kg', 'quantity' => 20, 'photo' => UploadedFile::fake()->image('factura.jpg')],
            ['name' => 'Arena', 'quantity' => 3],
        ])->assertSessionHasNoErrors();

        $entry = Entry::where('cedula', '7001')->firstOrFail();
        $this->assertSame('proveedor', $entry->type);
        $this->assertSame($this->work->id, $entry->work_id);
        $this->assertSame('Ferretería El Tornillo', $entry->supplier_company);

        $cement = WorkItem::where('name', 'Cemento 50kg')->firstOrFail();
        $this->assertNull($cement->work_worker_id);
        $this->assertSame('material', $cement->kind);
        $this->assertSame($this->house->id, $cement->property_id);
        $this->assertSame(20, $cement->quantity_inside);
        Storage::disk('public')->assertExists($cement->photo_path);
        $this->assertDatabaseHas('item_movements', ['work_item_id' => $cement->id, 'notes' => 'Entrega de Ferretería El Tornillo']);
    }

    public function test_repeated_material_is_added_to_the_same_item(): void
    {
        $this->deliver([['name' => 'Cemento 50kg', 'quantity' => 10]]);
        [, $exit] = $this->exitOf('7001');
        $exit->assertSessionHasNoErrors();

        $this->deliver([['name' => 'cemento 50KG', 'quantity' => 5]])->assertSessionHasNoErrors();

        $this->assertSame(1, WorkItem::count());
        $this->assertSame(15, WorkItem::first()->quantity_inside);
    }

    public function test_supplier_items_are_always_material(): void
    {
        $this->deliver([['name' => 'Taladro', 'kind' => 'herramienta', 'quantity' => 1]])->assertSessionHasNoErrors();

        $this->assertSame('material', WorkItem::first()->kind);
    }

    public function test_supplier_needs_a_current_work_and_company(): void
    {
        $this->deliver([], ['supplier_company' => '', 'work_id' => null])
            ->assertSessionHasErrors(['supplier_company', 'work_id']);

        $this->work->update(['status' => 'suspendida']);
        $this->deliver([['name' => 'Arena', 'quantity' => 1]])->assertSessionHasErrors('work_id');

        $this->assertSame(0, Entry::count());
    }

    public function test_repeat_supplier_is_remembered_by_plate(): void
    {
        $this->deliver([['name' => 'Arena', 'quantity' => 1]]);
        $this->exitOf('7001');

        $this->actingAs($this->guard)->getJson('/vigilante/entries/lookup-plate?plate=fer-123')
            ->assertJson([
                'cedula' => '7001',
                'type' => 'proveedor',
                'supplier' => ['company' => 'Ferretería El Tornillo', 'work_id' => $this->work->id],
            ]);
    }

    public function test_create_page_lists_current_works_for_suppliers(): void
    {
        $this->makeWork($this->house, 'pendiente');

        $this->actingAs($this->guard)->get('/vigilante/entries/create')
            ->assertInertia(fn (Assert $page) => $page->has('current_works', 1)->where('current_works.0.id', $this->work->id));
    }

    // ── Salidas de material ─────────────────────────────────────────────────

    private function requestExit(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->post("/works/{$this->work->id}/material-exits", [
            'description' => 'Escombro de demolición',
            'quantity' => '6 bultos',
            'reason' => 'escombro',
            ...$overrides,
        ]);
    }

    public function test_owner_request_is_approved_and_guard_request_waits(): void
    {
        $this->requestExit($this->owner)->assertSessionHasNoErrors();
        $this->requestExit($this->guard, ['description' => 'Sobrante de baldosa'])->assertSessionHasNoErrors();

        $this->assertSame('aprobada', MaterialExit::where('description', 'Escombro de demolición')->value('status'));
        $this->assertSame('pendiente', MaterialExit::where('description', 'Sobrante de baldosa')->value('status'));
    }

    public function test_guard_cannot_approve_and_owner_or_admin_can(): void
    {
        $this->requestExit($this->guard);
        $exit = MaterialExit::firstOrFail();

        $this->actingAs($this->guard)
            ->post("/works/{$this->work->id}/material-exits/{$exit->id}/decision", ['action' => 'aprobar'])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post("/works/{$this->work->id}/material-exits/{$exit->id}/decision", ['action' => 'aprobar'])
            ->assertSessionHasNoErrors();

        $exit->refresh();
        $this->assertSame('aprobada', $exit->status);
        $this->assertSame($this->owner->id, $exit->approved_by);
    }

    public function test_owner_of_another_house_cannot_request_or_approve(): void
    {
        $stranger = $this->propietarioWithProperty('Casa-09');
        $this->requestExit($stranger)->assertForbidden();

        $this->requestExit($this->guard);
        $exit = MaterialExit::firstOrFail();

        $this->actingAs($stranger)
            ->post("/works/{$this->work->id}/material-exits/{$exit->id}/decision", ['action' => 'aprobar'])
            ->assertForbidden();
    }

    public function test_approved_material_leaves_with_supplier(): void
    {
        $this->requestExit($this->owner);
        $exit = MaterialExit::firstOrFail();
        $this->deliver([]);

        $entry = Entry::where('cedula', '7001')->active()->firstOrFail();
        $this->actingAs($this->guard)->getJson("/vigilante/exits/{$entry->id}/tools")
            ->assertJsonPath('material_exits.0.description', 'Escombro de demolición')
            ->assertJsonCount(0, 'own');

        $this->actingAs($this->guard)->get('/vigilante/exits')
            ->assertInertia(fn (Assert $page) => $page
                ->where('inside.0.work.is_supplier', true)
                ->where('inside.0.work.material_exits', 1));

        [, $response] = $this->exitOf('7001', ['material_exits' => [['entry_id' => $entry->id, 'id' => $exit->id]]]);
        $response->assertSessionHasNoErrors();

        $exit->refresh();
        $this->assertSame('ejecutada', $exit->status);
        $this->assertSame($entry->id, $exit->entry_id);
        $this->assertSame($this->guard->id, $exit->executed_by);
    }

    public function test_pending_material_exit_cannot_leave(): void
    {
        $this->requestExit($this->guard);
        $exit = MaterialExit::firstOrFail();
        $this->deliver([]);
        $entry = Entry::where('cedula', '7001')->active()->firstOrFail();

        [, $response] = $this->exitOf('7001', ['material_exits' => [['entry_id' => $entry->id, 'id' => $exit->id]]]);
        $response->assertSessionHasErrors('material_exits');

        $this->assertSame('pendiente', $exit->fresh()->status);
        $this->assertNull($entry->fresh()->exit);
    }

    public function test_material_exit_of_another_house_cannot_leave_with_this_person(): void
    {
        $otherHouse = Property::create(['number' => 'Casa-09', 'type' => 'casa']);
        $otherWork = $this->makeWork($otherHouse);
        $foreign = $otherWork->materialExits()->create([
            'description' => 'Tubería', 'quantity' => '4', 'reason' => 'sobrante', 'status' => 'aprobada', 'requested_by' => $this->guard->id,
        ]);
        $this->deliver([]);
        $entry = Entry::where('cedula', '7001')->active()->firstOrFail();

        [, $response] = $this->exitOf('7001', ['material_exits' => [['entry_id' => $entry->id, 'id' => $foreign->id]]]);
        $response->assertSessionHasErrors('material_exits');
        $this->assertSame('aprobada', $foreign->fresh()->status);
    }

    // ── Acta y alertas ──────────────────────────────────────────────────────

    public function test_acta_pdf_downloads_for_allowed_users(): void
    {
        $this->deliver([['name' => 'Cemento', 'quantity' => 2]]);

        $this->actingAs($this->owner)->get("/works/{$this->work->id}/acta")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->guard)->get("/works/{$this->work->id}/acta?date=".today()->toDateString())
            ->assertOk()
            ->assertDownload("acta_obra_{$this->work->id}_".today()->format('Ymd').'.pdf');

        $this->actingAs($this->propietarioWithProperty('Casa-77'))->get("/works/{$this->work->id}/acta")->assertForbidden();
    }

    public function test_tools_left_in_a_closed_work_raise_an_alert(): void
    {
        WorkItem::create([
            'work_id' => $this->work->id, 'property_id' => $this->house->id, 'work_worker_id' => $this->juan->id,
            'name' => 'Taladro', 'kind' => 'herramienta', 'quantity_inside' => 2,
        ]);
        $this->work->update(['status' => 'cerrada']);

        $this->actingAs($this->admin())->get('/works')
            ->assertInertia(fn (Assert $page) => $page->where('works.0.needs_attention', true));

        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('stats.tools_alert', 2));
    }
}
