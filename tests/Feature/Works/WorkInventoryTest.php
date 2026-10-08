<?php

namespace Tests\Feature\Works;

use App\Models\Entry;
use App\Models\ItemMovement;
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
 * Inventario de herramientas y materiales por casa y por trabajador.
 */
class WorkInventoryTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    private User $guard;

    private Property $house;

    private Work $work;

    private WorkWorker $juan;

    private WorkWorker $luis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');

        $this->guard = $this->vigilante();
        $this->house = Property::create(['number' => 'Casa-02', 'type' => 'casa']);
        $this->work = $this->makeWork($this->house);
        $this->juan = $this->work->workers()->create(['first_name' => 'Juan', 'last_name' => 'Pérez', 'cedula' => '9001']);
        $this->luis = $this->work->workers()->create(['first_name' => 'Luis', 'last_name' => 'Gómez', 'cedula' => '9002']);
    }

    private function makeWork(Property $property, string $status = 'aprobada'): Work
    {
        return Work::create([
            'property_id' => $property->id, 'title' => 'Remodelación', 'type' => 'locativa',
            'contractor_name' => 'Constructora Ruiz', 'start_date' => today(), 'status' => $status,
            'created_by' => $this->admin()->id,
        ]);
    }

    private function enter(WorkWorker $worker, array $items, array $overrides = [])
    {
        return $this->actingAs($this->guard)->post('/vigilante/entries', [
            'first_name' => $worker->first_name,
            'last_name' => $worker->last_name,
            'cedula' => $worker->cedula,
            'apartment' => $this->house->number,
            'type' => 'autorizado',
            'vehicle' => 'ninguno',
            'work_worker_id' => $worker->id,
            'items' => $items,
            ...$overrides,
        ]);
    }

    private function entryOf(WorkWorker $worker): Entry
    {
        return Entry::where('cedula', $worker->cedula)->active()->firstOrFail();
    }

    private function giveTool(WorkWorker $worker, string $name, int $qty = 1, ?Property $house = null): WorkItem
    {
        return WorkItem::create([
            'work_id' => $worker->work_id,
            'property_id' => ($house ?? $this->house)->id,
            'work_worker_id' => $worker->id,
            'name' => $name,
            'kind' => 'herramienta',
            'quantity_inside' => $qty,
        ]);
    }

    // ── Ingreso ─────────────────────────────────────────────────────────────

    public function test_lookup_by_cedula_returns_the_work_and_its_house(): void
    {
        $this->giveTool($this->juan, 'Taladro');

        $this->actingAs($this->guard)
            ->getJson('/vigilante/entries/lookup?cedula=9001')
            ->assertJson([
                'first_name' => 'Juan',
                'apartment' => 'Casa-02',
                'type' => 'autorizado',
                'work' => [
                    'worker_id' => $this->juan->id,
                    'is_current' => true,
                    'property_number' => 'Casa-02',
                    'tools_inside' => [['name' => 'Taladro']],
                ],
            ]);
    }

    public function test_worker_enters_tools_and_materials_with_optional_photo(): void
    {
        $this->enter($this->juan, [
            ['name' => 'Taladro DeWalt', 'serial' => 'dw-4471', 'kind' => 'herramienta', 'quantity' => 1, 'photo' => UploadedFile::fake()->image('taladro.jpg')],
            ['name' => 'Cemento 50kg', 'kind' => 'material', 'quantity' => 10],
        ])->assertSessionHasNoErrors();

        $drill = WorkItem::where('name', 'Taladro DeWalt')->firstOrFail();
        $this->assertSame($this->juan->id, $drill->work_worker_id);
        $this->assertSame($this->house->id, $drill->property_id);
        $this->assertSame('DW-4471', $drill->serial);
        $this->assertSame(1, $drill->quantity_inside);
        Storage::disk('public')->assertExists($drill->photo_path);

        $this->assertSame(10, WorkItem::where('name', 'Cemento 50kg')->value('quantity_inside'));
        $this->assertSame(2, ItemMovement::where('direction', 'ingreso')->count());
        $this->assertSame($this->juan->id, $this->entryOf($this->juan)->work_worker_id);
    }

    public function test_reentry_reuses_the_same_item(): void
    {
        $drill = $this->giveTool($this->juan, 'Taladro', 0);

        $this->enter($this->juan, [['item_id' => $drill->id, 'quantity' => 1]])->assertSessionHasNoErrors();

        $this->assertSame(1, $drill->fresh()->quantity_inside);
        $this->assertSame(1, WorkItem::count());
    }

    public function test_items_are_rejected_when_the_work_is_not_approved_or_current(): void
    {
        $this->work->update(['status' => 'pendiente']);

        $this->enter($this->juan, [['name' => 'Taladro', 'kind' => 'herramienta', 'quantity' => 1]])
            ->assertSessionHasErrors('work_worker_id');

        $this->assertSame(0, WorkItem::count());
        $this->assertSame(0, Entry::count());
    }

    public function test_items_without_a_work_worker_are_rejected(): void
    {
        $this->enter($this->juan, [['name' => 'Taladro', 'kind' => 'herramienta', 'quantity' => 1]], ['work_worker_id' => null])
            ->assertSessionHasErrors('items');
    }

    public function test_photo_must_be_an_image(): void
    {
        $this->enter($this->juan, [['name' => 'Taladro', 'kind' => 'herramienta', 'quantity' => 1, 'photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('items.0.photo');
    }

    // ── Salida ──────────────────────────────────────────────────────────────

    public function test_exit_list_and_tools_endpoint_show_tools_by_worker(): void
    {
        $this->enter($this->juan, [['name' => 'Taladro', 'kind' => 'herramienta', 'quantity' => 1]]);
        $this->giveTool($this->luis, 'Escalera');
        $entry = $this->entryOf($this->juan);

        $this->actingAs($this->guard)->get('/vigilante/exits')
            ->assertInertia(fn (Assert $page) => $page->where('inside.0.work.tools_inside', 1));

        $this->actingAs($this->guard)->getJson("/vigilante/exits/{$entry->id}/tools")
            ->assertJsonPath('own.0.name', 'Taladro')
            ->assertJsonPath('others.0.name', 'Escalera')
            ->assertJsonPath('others.0.owner', 'Luis Gómez');
    }

    public function test_worker_cannot_exit_without_deciding_about_own_tools(): void
    {
        $this->enter($this->juan, [['name' => 'Taladro', 'kind' => 'herramienta', 'quantity' => 1]]);
        $entry = $this->entryOf($this->juan);

        $this->actingAs($this->guard)
            ->post('/vigilante/exits', ['entry_ids' => [$entry->id]])
            ->assertSessionHasErrors('tool_moves');

        $this->assertNull($entry->fresh()->exit);
    }

    public function test_tools_leave_or_stay_with_their_owner(): void
    {
        $this->enter($this->juan, [
            ['name' => 'Taladro', 'kind' => 'herramienta', 'quantity' => 1],
            ['name' => 'Pulidora', 'kind' => 'herramienta', 'quantity' => 1],
        ]);
        $entry = $this->entryOf($this->juan);
        [$drill, $grinder] = [WorkItem::where('name', 'Taladro')->first(), WorkItem::where('name', 'Pulidora')->first()];

        $this->actingAs($this->guard)->post('/vigilante/exits', [
            'entry_ids' => [$entry->id],
            'tool_moves' => [
                ['entry_id' => $entry->id, 'item_id' => $drill->id, 'action' => 'sale', 'quantity' => 1],
                ['entry_id' => $entry->id, 'item_id' => $grinder->id, 'action' => 'queda'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($entry->fresh()->exit);
        $this->assertSame(0, $drill->fresh()->quantity_inside);
        $this->assertSame(1, $grinder->fresh()->quantity_inside);
        $this->assertDatabaseHas('item_movements', ['work_item_id' => $grinder->id, 'direction' => 'queda']);
        $this->assertDatabaseHas('item_movements', ['work_item_id' => $drill->id, 'direction' => 'salida', 'is_transfer' => false]);
    }

    public function test_colleague_tool_leaving_is_registered_as_transfer(): void
    {
        $ladder = $this->giveTool($this->luis, 'Escalera');
        $this->enter($this->juan, []);
        $entry = $this->entryOf($this->juan);

        $this->actingAs($this->guard)->post('/vigilante/exits', [
            'entry_ids' => [$entry->id],
            'tool_moves' => [['entry_id' => $entry->id, 'item_id' => $ladder->id, 'action' => 'sale', 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $ladder->fresh()->quantity_inside);
        $movement = ItemMovement::where('work_item_id', $ladder->id)->where('direction', 'salida')->firstOrFail();
        $this->assertTrue($movement->is_transfer);
        $this->assertSame($this->juan->id, $movement->work_worker_id);
        $this->assertStringContainsString('ingresó Luis Gómez', $movement->notes);
    }

    public function test_tools_from_another_house_cannot_leave(): void
    {
        $otherHouse = Property::create(['number' => 'Casa-09', 'type' => 'casa']);
        $otherWork = $this->makeWork($otherHouse);
        $stranger = $otherWork->workers()->create(['first_name' => 'Otro', 'last_name' => 'Obrero', 'cedula' => '9900']);
        $foreignTool = $this->giveTool($stranger, 'Mezcladora', 1, $otherHouse);

        $this->enter($this->juan, []);
        $entry = $this->entryOf($this->juan);

        $this->actingAs($this->guard)->post('/vigilante/exits', [
            'entry_ids' => [$entry->id],
            'tool_moves' => [['entry_id' => $entry->id, 'item_id' => $foreignTool->id, 'action' => 'sale', 'quantity' => 1]],
        ])->assertSessionHasErrors('tool_moves');

        $this->assertSame(1, $foreignTool->fresh()->quantity_inside);
        $this->assertNull($entry->fresh()->exit);
    }

    public function test_cannot_take_out_more_than_is_inside(): void
    {
        $this->enter($this->juan, [['name' => 'Andamio', 'kind' => 'herramienta', 'quantity' => 2]]);
        $entry = $this->entryOf($this->juan);
        $scaffold = WorkItem::first();

        $this->actingAs($this->guard)->post('/vigilante/exits', [
            'entry_ids' => [$entry->id],
            'tool_moves' => [['entry_id' => $entry->id, 'item_id' => $scaffold->id, 'action' => 'sale', 'quantity' => 5]],
        ])->assertSessionHasErrors('tool_moves');

        $this->assertSame(2, $scaffold->fresh()->quantity_inside);
    }

    public function test_worker_with_tools_inside_cannot_be_removed_from_work(): void
    {
        $this->giveTool($this->juan, 'Taladro');

        $this->actingAs($this->admin())
            ->delete("/works/{$this->work->id}/workers/{$this->juan->id}")
            ->assertSessionHasErrors('worker');

        $this->assertModelExists($this->juan);
    }

    public function test_work_page_groups_inventory_by_worker(): void
    {
        $this->giveTool($this->juan, 'Taladro');
        $this->giveTool($this->luis, 'Escalera');

        $this->actingAs($this->admin())->get("/works/{$this->work->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('works/Show')
                ->where('workers.0.full_name', 'Juan Pérez')
                ->where('workers.0.tools.0.name', 'Taladro')
                ->where('workers.1.tools.0.name', 'Escalera'));
    }
}
