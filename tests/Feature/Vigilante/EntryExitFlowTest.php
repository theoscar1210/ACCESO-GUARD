<?php

namespace Tests\Feature\Vigilante;

use App\Models\Entry;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\PropertyRental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class EntryExitFlowTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Property::create(['number' => '101', 'type' => 'apartamento']);
    }

    private function validEntry(array $overrides = []): array
    {
        return [
            'first_name' => 'Carlos',
            'last_name' => 'Ruiz',
            'cedula' => '12345678',
            'apartment' => '101',
            'type' => 'visitante',
            'vehicle' => 'automovil',
            'plate' => 'ABC123',
            'observations' => null,
            ...$overrides,
        ];
    }

    public function test_vigilante_can_register_an_entry(): void
    {
        $vig = $this->vigilante();

        $this->actingAs($vig)
            ->post('/vigilante/entries', $this->validEntry())
            ->assertRedirect(route('vigilante.entries.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entries', [
            'cedula' => '12345678',
            'user_id' => $vig->id,
            'registered_by' => $vig->username,
        ]);
    }

    // ── Destino: inmueble y/o administración ────────────────────────────────

    public function test_create_page_lists_registered_properties(): void
    {
        Property::create(['number' => '12', 'block' => 'B', 'type' => 'casa']);

        $this->actingAs($this->vigilante())->get('/vigilante/entries/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('vigilante/Entries/Create')
                ->has('properties', 2)
                ->where('properties.0.label', '101')
                ->where('properties.1.label', 'B - 12')
                ->where('properties.1.type', 'casa'));
    }

    public function test_entry_can_go_only_to_administration(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/entries', $this->validEntry(['apartment' => '', 'to_administration' => true]))
            ->assertSessionHasNoErrors();

        $entry = Entry::firstOrFail();
        $this->assertNull($entry->apartment);
        $this->assertTrue($entry->to_administration);
        $this->assertSame('Administración', $entry->destination);
    }

    public function test_entry_can_go_to_property_and_administration(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/entries', $this->validEntry(['to_administration' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame('101 · Administración', Entry::firstOrFail()->destination);
    }

    public function test_entry_requires_a_destination(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/entries', $this->validEntry(['apartment' => '', 'to_administration' => false]))
            ->assertSessionHasErrors('apartment');

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_entry_destination_must_be_a_registered_property(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/entries', $this->validEntry(['apartment' => '999']))
            ->assertSessionHasErrors('apartment');
    }

    public function test_destination_is_shown_in_exits_list(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['apartment' => null, 'to_administration' => true]);

        $this->actingAs($vig)->get('/vigilante/exits')
            ->assertInertia(fn (Assert $page) => $page->where('inside.0.apartment', 'Administración'));
    }

    public function test_plate_is_stored_uppercase(): void
    {
        $this->actingAs($this->vigilante())->post('/vigilante/entries', $this->validEntry(['plate' => ' abc123 ']));

        $this->assertDatabaseHas('entries', ['plate' => 'ABC123']);
    }

    public function test_exits_list_includes_plate_for_plate_search(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['plate' => 'XYZ789', 'vehicle' => 'automovil']);
        $this->makeEntry($vig, ['plate' => 'XYZ789', 'vehicle' => 'automovil']);

        $this->actingAs($vig)->get('/vigilante/exits')
            ->assertInertia(fn (Assert $page) => $page
                ->component('vigilante/Exits/Index')
                ->has('inside', 2)
                ->where('inside.0.plate', 'XYZ789')
                ->where('inside.1.plate', 'XYZ789'));
    }

    public function test_all_occupants_of_a_vehicle_can_exit_together(): void
    {
        $vig = $this->vigilante();
        $driver = $this->makeEntry($vig, ['plate' => 'XYZ789', 'vehicle' => 'automovil']);
        $passenger = $this->makeEntry($vig, ['plate' => 'XYZ789', 'vehicle' => 'automovil']);
        $other = $this->makeEntry($vig, ['plate' => 'OTR111', 'vehicle' => 'moto']);

        $this->actingAs($vig)
            ->post('/vigilante/exits', ['entry_ids' => [$driver->id, $passenger->id]])
            ->assertSessionHas('success', '2 salidas registradas.');

        $this->assertSame([$other->id], Entry::active()->pluck('id')->all());
    }

    public function test_entry_requires_valid_fields(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/entries', $this->validEntry([
                'first_name' => '',
                'type' => 'invalido',
                'vehicle' => 'avion',
                'cedula' => str_repeat('1', 21),
            ]))
            ->assertSessionHasErrors(['first_name', 'type', 'vehicle', 'cedula']);

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_cannot_register_second_entry_while_person_is_inside(): void
    {
        $vig = $this->vigilante();
        $this->actingAs($vig)->post('/vigilante/entries', $this->validEntry());

        $this->actingAs($vig)
            ->post('/vigilante/entries', $this->validEntry())
            ->assertSessionHasErrors('active_entry');

        $this->assertDatabaseCount('entries', 1);
    }

    public function test_entry_marks_active_authorization_as_used(): void
    {
        $owner = $this->propietario();
        $auth = $this->makeAuthorization($owner, ['cedula' => '12345678']);

        $this->actingAs($this->vigilante())->post('/vigilante/entries', $this->validEntry());

        $this->assertSame('usado', $auth->fresh()->status);
    }

    public function test_vigilante_can_register_exit_and_person_can_reenter(): void
    {
        $vig = $this->vigilante();
        $entry = $this->makeEntry($vig, ['cedula' => '12345678']);

        $this->actingAs($vig)
            ->post('/vigilante/exits', ['entry_ids' => [$entry->id]])
            ->assertRedirect(route('vigilante.exits.index'));

        $this->assertDatabaseHas('exits', ['entry_id' => $entry->id, 'exited_by' => $vig->username]);
        $this->assertSame(0, Entry::active()->count());

        $this->actingAs($vig)
            ->post('/vigilante/entries', $this->validEntry())
            ->assertSessionHasNoErrors();
    }

    public function test_exit_is_not_duplicated_for_entry_already_exited(): void
    {
        $vig = $this->vigilante();
        $entry = $this->makeEntry($vig);

        $this->actingAs($vig)->post('/vigilante/exits', ['entry_ids' => [$entry->id]]);
        $this->actingAs($vig)->post('/vigilante/exits', ['entry_ids' => [$entry->id]]);

        $this->assertDatabaseCount('exits', 1);
    }

    public function test_exit_success_message_counts_only_processed_entries(): void
    {
        $vig = $this->vigilante();
        $inside = $this->makeEntry($vig);
        $alreadyOut = $this->makeEntry($vig);
        $this->actingAs($vig)->post('/vigilante/exits', ['entry_ids' => [$alreadyOut->id]]);

        $this->actingAs($vig)
            ->post('/vigilante/exits', ['entry_ids' => [$inside->id, $alreadyOut->id]])
            ->assertSessionHas('success', '1 salida registrada.');
    }

    public function test_exit_rejects_nonexistent_entries(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/exits', ['entry_ids' => [99999]])
            ->assertSessionHasErrors('entry_ids.0');
    }

    public function test_entries_index_shows_today_entries_and_inside_stats(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['type' => 'visitante']);
        $this->makeEntry($vig, ['type' => 'propietario']);
        $this->makeEntry($vig, ['entry_at' => now()->subDays(2), 'type' => 'autorizado']);

        $this->actingAs($vig)->get('/vigilante/entries')
            ->assertInertia(fn (Assert $page) => $page
                ->component('vigilante/Entries/Index')
                ->has('entries', 2)
                ->where('stats.inside', 3)
                ->where('stats.autorizado', 1));
    }

    public function test_lookup_by_cedula_identifies_registered_owner(): void
    {
        $owner = $this->propietarioWithProperty('A-5');

        $this->actingAs($this->vigilante())
            ->getJson('/vigilante/entries/lookup?cedula='.$owner->cedula)
            ->assertOk()
            ->assertJson([
                'first_name' => $owner->first_name,
                'type' => 'propietario',
                'apartment' => 'A-5',
                'known_in_system' => true,
            ]);
    }

    public function test_lookup_by_cedula_identifies_family_member(): void
    {
        $owner = $this->propietarioWithProperty('B-2');
        FamilyMember::create([
            'user_id' => $owner->id, 'first_name' => 'Hijo', 'last_name' => 'X',
            'cedula' => '55555555', 'relationship' => 'hijo',
        ]);

        $this->actingAs($this->vigilante())
            ->getJson('/vigilante/entries/lookup?cedula=55555555')
            ->assertJson(['type' => 'autorizado', 'apartment' => 'B-2', 'known_in_system' => true]);
    }

    public function test_lookup_returns_null_for_short_or_unknown_cedula(): void
    {
        $vig = $this->vigilante();

        $this->actingAs($vig)->getJson('/vigilante/entries/lookup?cedula=12')->assertExactJson([]);
        $this->actingAs($vig)->getJson('/vigilante/entries/lookup?cedula=00000000')->assertExactJson([]);
    }

    public function test_lookup_by_plate_uses_latest_entry(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['plate' => 'XYZ789', 'cedula' => '777', 'first_name' => 'Pedro', 'apartment' => '303']);

        $this->actingAs($vig)
            ->getJson('/vigilante/entries/lookup-plate?plate=xyz789')
            ->assertJson(['cedula' => '777', 'first_name' => 'Pedro', 'apartment' => '303']);
    }

    public function test_lookup_by_plate_finds_authorization_with_person_and_property(): void
    {
        $owner = $this->propietarioWithProperty('A-7');
        $this->makeAuthorization($owner, [
            'first_name' => 'Rosa', 'last_name' => 'Mejía', 'cedula' => '4455', 'plate' => 'KLM-456', 'vehicle' => 'camioneta',
        ]);

        // Sin guiones, en minúsculas y sin ingresos previos de esa placa
        $this->actingAs($this->vigilante())
            ->getJson('/vigilante/entries/lookup-plate?plate=klm456')
            ->assertOk()
            ->assertJson([
                'cedula' => '4455',
                'first_name' => 'Rosa',
                'last_name' => 'Mejía',
                'apartment' => 'A-7',
                'vehicle' => 'camioneta',
                'type' => 'autorizado',
                'source' => 'authorization',
                'authorization' => ['plate' => 'KLM-456', 'vehicle' => 'camioneta'],
            ]);
    }

    public function test_lookup_by_plate_fills_form_with_last_entry(): void
    {
        $vig = $this->vigilante();
        Property::create(['number' => '202', 'type' => 'apartamento']);
        $this->makeEntry($vig, [
            'cedula' => '3030', 'first_name' => 'Old', 'apartment' => '101',
            'plate' => 'QWE-987', 'vehicle' => 'automovil', 'entry_at' => now()->subDays(3),
        ]);
        $this->makeEntry($vig, [
            'cedula' => '3030', 'first_name' => 'Pablo', 'last_name' => 'Rey', 'apartment' => '202',
            'plate' => 'QWE-987', 'vehicle' => 'camioneta', 'type' => 'visitante', 'entry_at' => now()->subDay(),
        ]);

        $this->actingAs($vig)
            ->getJson('/vigilante/entries/lookup-plate?plate=qwe 987')
            ->assertJson([
                'cedula' => '3030',
                'first_name' => 'Pablo',
                'last_name' => 'Rey',
                'apartment' => '202',
                'to_administration' => false,
                'vehicle' => 'camioneta',
                'type' => 'visitante',
                'source' => 'entry',
                'last_entry_at' => now()->subDay()->format('d/m/Y H:i'),
            ]);
    }

    public function test_lookup_by_plate_returns_administration_destination(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['apartment' => null, 'to_administration' => true, 'plate' => 'ADM100', 'vehicle' => 'moto']);

        $this->actingAs($vig)
            ->getJson('/vigilante/entries/lookup-plate?plate=ADM100')
            ->assertJson(['apartment' => null, 'to_administration' => true, 'vehicle' => 'moto']);
    }

    public function test_last_entry_takes_priority_over_authorization_with_same_plate(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['cedula' => '1111', 'first_name' => 'Conductor', 'plate' => 'PRI123', 'vehicle' => 'automovil']);
        $this->makeAuthorization($this->propietarioWithProperty('A-9'), ['cedula' => '2222', 'first_name' => 'Otro', 'plate' => 'PRI123']);

        $this->actingAs($vig)
            ->getJson('/vigilante/entries/lookup-plate?plate=PRI123')
            ->assertJson(['cedula' => '1111', 'first_name' => 'Conductor', 'source' => 'entry']);
    }

    public function test_entry_with_plate_requires_vehicle_type(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/entries', $this->validEntry(['plate' => 'ABC123', 'vehicle' => 'ninguno']))
            ->assertSessionHasErrors('vehicle');

        $this->actingAs($this->vigilante())
            ->post('/vigilante/entries', $this->validEntry(['plate' => '', 'vehicle' => 'ninguno']))
            ->assertSessionHasNoErrors();
    }

    public function test_lookup_by_plate_uses_property_of_authorizing_tenant(): void
    {
        $tenant = $this->residente();
        $property = Property::create(['number' => '505', 'type' => 'apartamento']);
        PropertyRental::create(['property_id' => $property->id, 'user_id' => $tenant->id, 'start_date' => today(), 'is_active' => true]);
        $this->makeAuthorization($tenant, ['cedula' => '6677', 'plate' => 'RES111']);

        $this->actingAs($this->vigilante())
            ->getJson('/vigilante/entries/lookup-plate?plate=RES-111')
            ->assertJson(['cedula' => '6677', 'apartment' => '505']);
    }

    public function test_lookup_by_plate_ignores_used_or_expired_authorizations(): void
    {
        $owner = $this->propietarioWithProperty('A-8');
        $this->makeAuthorization($owner, ['plate' => 'OLD111', 'status' => 'usado']);
        $this->makeAuthorization($owner, ['plate' => 'OLD222', 'start_date' => today()->subDays(5), 'end_date' => now()->subDay()]);

        $vig = $this->vigilante();
        $this->actingAs($vig)->getJson('/vigilante/entries/lookup-plate?plate=OLD111')->assertExactJson([]);
        $this->actingAs($vig)->getJson('/vigilante/entries/lookup-plate?plate=OLD222')->assertExactJson([]);
    }

    public function test_lookup_by_cedula_fills_property_from_authorization(): void
    {
        $owner = $this->propietarioWithProperty('C-3');
        $this->makeAuthorization($owner, ['first_name' => 'Iván', 'cedula' => '8899', 'plate' => 'IVN900']);

        $this->actingAs($this->vigilante())
            ->getJson('/vigilante/entries/lookup?cedula=8899')
            ->assertJson([
                'first_name' => 'Iván',
                'apartment' => 'C-3',
                'type' => 'autorizado',
                'authorization' => ['plate' => 'IVN900'],
            ]);
    }

    public function test_vigilante_sees_only_active_authorizations(): void
    {
        $owner = $this->propietario();
        $this->makeAuthorization($owner);
        $this->makeAuthorization($owner, ['status' => 'usado']);
        $this->makeAuthorization($owner, ['end_date' => now()->subDay(), 'start_date' => today()->subDays(3)]);
        $this->makeAuthorization($owner, ['start_date' => today()->addDays(3), 'end_date' => now()->addDays(5)]);

        $this->actingAs($this->vigilante())->get('/vigilante/authorizations')
            ->assertInertia(fn (Assert $page) => $page->has('authorizations', 1));
    }

    public function test_report_export_validates_input(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/reports/export', ['format' => 'csv', 'date_from' => '2026-05-10', 'date_to' => '2026-05-01'])
            ->assertSessionHasErrors(['format', 'date_to']);
    }

    public function test_report_export_downloads_pdf_and_excel(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig);

        $this->actingAs($vig)->post('/vigilante/reports/export', ['format' => 'pdf'])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($vig)->post('/vigilante/reports/export', ['format' => 'excel'])
            ->assertOk()
            ->assertDownload();
    }

    public function test_report_can_filter_by_residente_type(): void
    {
        $this->actingAs($this->vigilante())
            ->post('/vigilante/reports/export', ['format' => 'excel', 'type' => 'residente'])
            ->assertSessionHasNoErrors();
    }
}
