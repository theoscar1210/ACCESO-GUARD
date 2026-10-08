<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CondominiumDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los datos de demostración deben respetar las mismas reglas que la app.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, AdminSeeder::class, CondominiumDemoSeeder::class]);
    }

    public function test_owners_and_tenants_enter_their_own_property_with_their_own_type(): void
    {
        $entries = Entry::whereIn('type', ['propietario', 'residente'])->get();
        $this->assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $user = User::where('cedula', $entry->cedula)->firstOrFail();

            $this->assertTrue($user->hasRole(ucfirst($entry->type)), "{$user->full_name} entró como {$entry->type}");
            $this->assertSame($user->property_number, $entry->apartment, "{$user->full_name} entró a un inmueble que no es suyo");
        }
    }

    public function test_no_one_is_inside_twice_and_nothing_is_in_the_future(): void
    {
        $active = Entry::active()->pluck('cedula');

        $this->assertSame($active->count(), $active->unique()->count(), 'Hay cédulas con más de un ingreso activo');
        $this->assertSame(0, Entry::where('entry_at', '>', now())->count());
        $this->assertSame(0, Entry::whereHas('exit', fn ($q) => $q->where('exited_at', '>', now()))->count());
        $this->assertSame(0, Entry::whereHas('exit', fn ($q) => $q->whereNull('exited_by'))->count());
    }

    public function test_seeding_twice_does_not_duplicate_entries(): void
    {
        $count = Entry::count();

        $this->seed(CondominiumDemoSeeder::class);

        $this->assertSame($count, Entry::count());
    }
}
