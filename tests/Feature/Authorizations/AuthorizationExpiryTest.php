<?php

namespace Tests\Feature\Authorizations;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class AuthorizationExpiryTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_expired_authorization_is_shown_as_expired_before_the_task_runs(): void
    {
        $owner = $this->propietario();
        $this->makeAuthorization($owner, ['start_date' => today()->subDays(5), 'end_date' => now()->subHour()]);

        $this->actingAs($owner)->get('/propietario/authorizations')
            ->assertInertia(fn (Assert $page) => $page->where('authorizations.0.status', 'vencido'));

        $this->actingAs($owner)->get('/propietario/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('authorizations.0.status', 'vencido')
                ->where('stats.authorizations_active', 0));
    }

    public function test_residente_also_sees_expired_status(): void
    {
        $tenant = $this->residente();
        $this->makeAuthorization($tenant, ['start_date' => today()->subDays(5), 'end_date' => now()->subDay()]);

        $this->actingAs($tenant)->get('/residente/authorizations')
            ->assertInertia(fn (Assert $page) => $page->where('authorizations.0.status', 'vencido'));
    }

    public function test_expire_command_marks_only_expired_active_authorizations(): void
    {
        $owner = $this->propietario();
        $expired = $this->makeAuthorization($owner, ['start_date' => today()->subDays(5), 'end_date' => now()->subMinute()]);
        $current = $this->makeAuthorization($owner, ['end_date' => now()->addDay()]);
        $noEnd = $this->makeAuthorization($owner, ['end_date' => null]);
        $used = $this->makeAuthorization($owner, ['status' => 'usado', 'start_date' => today()->subDays(5), 'end_date' => now()->subDay()]);

        $this->artisan('authorizations:expire')
            ->expectsOutput('1 autorización(es) marcadas como vencidas.')
            ->assertSuccessful();

        $this->assertSame('vencido', $expired->fresh()->status);
        $this->assertSame('activo', $current->fresh()->status);
        $this->assertSame('activo', $noEnd->fresh()->status);
        $this->assertSame('usado', $used->fresh()->status);
    }

    public function test_expire_command_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'authorizations:expire'));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
    }
}
