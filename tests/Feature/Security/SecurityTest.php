<?php

namespace Tests\Feature\Security;

use App\Models\Announcement;
use App\Models\Condominium;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ── Autenticación ───────────────────────────────────────────────────────

    public function test_login_with_username_and_wrong_password_fails(): void
    {
        $user = $this->admin(['username' => 'jefe']);

        $this->post('/login', ['username' => 'jefe', 'password' => 'incorrecta']);
        $this->assertGuest();

        $this->post('/login', ['username' => 'jefe', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $this->admin(['username' => 'jefe']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'jefe', 'password' => 'mala']);
        }

        $this->post('/login', ['username' => 'jefe', 'password' => 'password'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs($this->admin())->post('/logout');
        $this->assertGuest();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    // ── Exposición de datos sensibles ───────────────────────────────────────

    public function test_shared_inertia_user_does_not_leak_secrets(): void
    {
        $user = $this->admin();
        $user->forceFill(['two_factor_secret' => encrypt('SECRETO'), 'two_factor_recovery_codes' => encrypt('[]')])->save();

        $this->actingAs($user)->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user')
                ->missing('auth.user.password')
                ->missing('auth.user.two_factor_secret')
                ->missing('auth.user.two_factor_recovery_codes')
                ->missing('auth.user.remember_token'));
    }

    public function test_propietario_without_property_does_not_see_all_entries(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['apartment' => '101']);
        $this->makeEntry($vig, ['apartment' => '202']);

        $this->actingAs($this->propietario())->get('/propietario/history')
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));
    }

    public function test_residente_without_rental_does_not_see_all_entries(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['apartment' => '101']);
        $this->makeEntry($vig, ['apartment' => '202']);

        $this->actingAs($this->residente())->get('/residente/history')
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));
    }

    public function test_propietario_history_only_shows_own_property(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig, ['apartment' => '101']);
        $this->makeEntry($vig, ['apartment' => '202']);

        $this->actingAs($this->propietarioWithProperty('101'))->get('/propietario/history')
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
    }

    public function test_user_cannot_read_announcement_not_addressed_to_them(): void
    {
        $admin = $this->admin();
        $tenant = $this->residente();
        $private = Announcement::create(['created_by' => $admin->id, 'title' => 'Solo propietarios', 'body' => 'confidencial', 'target' => 'propietario', 'send_push' => false]);

        $this->actingAs($tenant)->post("/announcements/{$private->id}/read");

        $this->actingAs($tenant)->get('/announcements')
            ->assertInertia(fn (Assert $page) => $page->has('announcements', 0));
    }

    // ── Multi-tenant ────────────────────────────────────────────────────────

    public function test_admin_cannot_see_users_or_entries_from_another_condominium(): void
    {
        $condoA = Condominium::create(['name' => 'A']);
        $condoB = Condominium::create(['name' => 'B']);

        $adminA = $this->admin();
        $adminA->forceFill(['condominium_id' => $condoA->id])->save();

        $foreign = $this->vigilante();
        $foreign->forceFill(['condominium_id' => $condoB->id])->save();
        $foreignEntry = $this->makeEntry($foreign);
        $foreignEntry->forceFill(['condominium_id' => $condoB->id])->save();

        $this->actingAs($adminA)->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page->has('users', 1));

        $this->actingAs($adminA)->get('/admin/entries')
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));

        $this->actingAs($adminA)->get("/admin/users/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_records_created_in_a_condominium_are_tagged_with_it(): void
    {
        $condo = Condominium::create(['name' => 'A']);
        $vig = $this->vigilante();
        $vig->forceFill(['condominium_id' => $condo->id])->save();

        $this->actingAs($vig)->post('/vigilante/entries', [
            'first_name' => 'X', 'last_name' => 'Y', 'cedula' => '1010', 'to_administration' => true,
            'type' => 'visitante', 'vehicle' => 'ninguno',
        ]);

        $this->assertDatabaseHas('entries', ['cedula' => '1010', 'condominium_id' => $condo->id]);
    }

    // ── Inyección / entradas maliciosas ─────────────────────────────────────

    public function test_sql_injection_in_search_filters_is_harmless(): void
    {
        $vig = $this->vigilante();
        $this->makeEntry($vig);
        $payload = "' OR 1=1 --";

        $this->actingAs($this->admin())
            ->get('/admin/entries?search='.urlencode($payload).'&apartment='.urlencode($payload))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));

        $this->actingAs($vig)
            ->post('/vigilante/reports/export', ['format' => 'excel', 'cedula' => $payload])
            ->assertOk();

        $this->assertDatabaseCount('entries', 1);
    }

    public function test_xss_payload_is_stored_verbatim_and_escaped_in_pdf(): void
    {
        $vig = $this->vigilante();
        $xss = '<script>alert(1)</script>';
        $this->makeEntry($vig, ['first_name' => $xss]);

        $html = view('reports.entries', [
            'entries' => \App\Models\Entry::with('exit')->get(),
            'title' => 'T',
            'data' => [],
        ])->render();

        $this->assertStringNotContainsString($xss, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_avatar_upload_rejects_non_images(): void
    {
        Storage::fake('public');
        $user = $this->propietario();

        foreach ([
            UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            UploadedFile::fake()->createWithContent('evil.svg', '<svg onload="alert(1)"></svg>'),
            UploadedFile::fake()->image('huge.jpg')->size(5000),
        ] as $file) {
            $this->actingAs($user)->post('/settings/avatar', ['avatar' => $file])->assertSessionHasErrors('avatar');
        }

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_avatar_upload_accepts_image_and_replaces_previous(): void
    {
        Storage::fake('public');
        $user = $this->propietario();

        $this->actingAs($user)->post('/settings/avatar', ['avatar' => UploadedFile::fake()->image('a.png')]);
        $first = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($user)->post('/settings/avatar', ['avatar' => UploadedFile::fake()->image('b.png')]);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($user)->delete('/settings/avatar');
        $this->assertNull($user->fresh()->avatar);
    }

    /**
     * El endpoint es una URL secreta que solo conoce el navegador que la generó.
     * Si otro usuario se suscribe desde ese mismo navegador (equipo compartido),
     * la suscripción debe pasar a él para que el anterior deje de recibir avisos ahí.
     */
    public function test_push_endpoint_follows_latest_user_on_shared_browser(): void
    {
        $previous = $this->propietario();
        $current = $this->residente();
        PushSubscription::create(['user_id' => $previous->id, 'endpoint' => 'https://push.example/abc']);

        $this->actingAs($current)->postJson('/push/subscribe', ['endpoint' => 'https://push.example/abc'])->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example/abc', 'user_id' => $current->id]);
    }

    public function test_push_unsubscribe_only_removes_own_subscription(): void
    {
        $victim = $this->propietario();
        PushSubscription::create(['user_id' => $victim->id, 'endpoint' => 'https://push.example/v']);

        $this->actingAs($this->residente())->deleteJson('/push/subscribe', ['endpoint' => 'https://push.example/v'])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example/v']);
    }

    // ── Cabeceras / configuración ───────────────────────────────────────────

    public function test_responses_include_basic_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_csrf_protection_is_active_outside_tests(): void
    {
        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'],
        );
    }
}
