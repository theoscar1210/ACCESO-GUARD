<?php

namespace Tests\Feature\Works;

use App\Models\Entry;
use App\Models\MaterialExit;
use App\Models\Property;
use App\Models\PropertyRental;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkItem;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\MaterialExitNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\Feature\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

/**
 * Salida de material: notificaciones (app, push, correo, WhatsApp), aprobación
 * del propietario o admin válida 48 h y retiro con cédula, placa, fecha y hora.
 */
class MaterialExitNotificationsTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    private User $guard;

    private User $owner;

    private User $tenant;

    private User $adminUser;

    private Property $house;

    private Work $work;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->guard = $this->vigilante();
        $this->adminUser = $this->admin(['phone' => '3001112233']);
        $this->owner = $this->propietarioWithProperty('Casa-02');
        $this->owner->update(['phone' => '3104445566']);
        $this->house = Property::where('number', 'Casa-02')->first();

        $this->tenant = $this->residente();
        PropertyRental::create(['property_id' => $this->house->id, 'user_id' => $this->tenant->id, 'start_date' => today(), 'is_active' => true]);

        $this->work = Work::create([
            'property_id' => $this->house->id, 'title' => 'Remodelación', 'type' => 'construccion',
            'contractor_name' => 'Constructora', 'start_date' => today(), 'status' => 'aprobada',
            'created_by' => $this->adminUser->id,
        ]);
    }

    private function request(User $user)
    {
        return $this->actingAs($user)->post("/works/{$this->work->id}/material-exits", [
            'description' => 'Escombro de demolición', 'quantity' => '6 bultos', 'reason' => 'escombro',
        ]);
    }

    private function approvedExit(): MaterialExit
    {
        Notification::fake();
        $this->request($this->owner);

        return MaterialExit::firstOrFail();
    }

    private function enterPerson(string $cedula, ?string $plate = 'VOL123'): Entry
    {
        return Entry::create([
            'user_id' => $this->guard->id, 'first_name' => 'Pedro', 'last_name' => 'Volquetero', 'cedula' => $cedula,
            'apartment' => 'Casa-02', 'type' => 'visitante', 'vehicle' => $plate ? 'camioneta' : 'ninguno',
            'plate' => $plate, 'registered_by' => $this->guard->username, 'entry_at' => now(),
        ]);
    }

    // ── Solicitud y aprobación ──────────────────────────────────────────────

    public function test_guard_request_notifies_owner_and_admin_but_not_tenant(): void
    {
        Notification::fake();

        $this->request($this->guard)->assertSessionHasNoErrors();

        $this->assertSame('pendiente', MaterialExit::first()->status);
        Notification::assertSentTo([$this->owner, $this->adminUser], MaterialExitNotification::class, fn ($n) => $n->event === 'solicitada');
        Notification::assertNotSentTo([$this->tenant, $this->guard], MaterialExitNotification::class);
    }

    public function test_request_goes_by_app_push_email_and_whatsapp(): void
    {
        config([
            'services.whatsapp.token' => 'token', 'services.whatsapp.phone_number_id' => '123',
            'webpush.vapid.public_key' => 'clave-publica',
        ]);
        PushSubscription::create(['user_id' => $this->owner->id, 'endpoint' => 'https://push.example/owner']);
        Notification::fake();

        $this->request($this->guard);

        Notification::assertSentTo($this->owner, MaterialExitNotification::class, function ($n, array $channels) {
            return $channels === ['database', WebPushChannel::class, 'mail', WhatsAppChannel::class];
        });
    }

    public function test_whatsapp_is_skipped_when_not_configured(): void
    {
        Notification::fake();
        $this->request($this->guard);

        Notification::assertSentTo($this->owner, MaterialExitNotification::class, fn ($n, array $channels) => ! in_array(WhatsAppChannel::class, $channels, true) && in_array('mail', $channels, true));
    }

    public function test_tenant_can_request_but_only_owner_or_admin_approve(): void
    {
        Notification::fake();
        $this->request($this->tenant)->assertSessionHasNoErrors();
        $exit = MaterialExit::firstOrFail();
        $this->assertSame('pendiente', $exit->status);

        $this->actingAs($this->tenant)
            ->post("/works/{$this->work->id}/material-exits/{$exit->id}/decision", ['action' => 'aprobar'])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post("/works/{$this->work->id}/material-exits/{$exit->id}/decision", ['action' => 'aprobar'])
            ->assertSessionHasNoErrors();

        $exit->refresh();
        $this->assertSame('aprobada', $exit->status);
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $exit->expires_at->timestamp, 5);
        Notification::assertSentTo([$this->guard, $this->tenant], MaterialExitNotification::class, fn ($n) => $n->event === 'aprobada');
    }

    public function test_rejection_notifies_who_asked(): void
    {
        Notification::fake();
        $this->request($this->guard);
        $exit = MaterialExit::firstOrFail();

        $this->actingAs($this->adminUser)
            ->post("/works/{$this->work->id}/material-exits/{$exit->id}/decision", ['action' => 'rechazar']);

        $this->assertSame('rechazada', $exit->fresh()->status);
        Notification::assertSentTo($this->guard, MaterialExitNotification::class, fn ($n) => $n->event === 'rechazada');
    }

    // ── Vigencia de 48 horas ────────────────────────────────────────────────

    public function test_approval_expires_after_48_hours(): void
    {
        $exit = $this->approvedExit();
        $person = $this->enterPerson('5005');

        $this->travel(49)->hours();

        $this->assertSame('vencida', $exit->fresh()->effective_status);
        $this->actingAs($this->guard)->get('/vigilante/exits')
            ->assertInertia(fn (Assert $page) => $page->has('material_exits', 0));

        $this->actingAs($this->guard)
            ->post("/vigilante/material-exits/{$exit->id}/retire", ['entry_id' => $person->id])
            ->assertSessionHasErrors('material_exit');

        $this->artisan('material-exits:expire')->assertSuccessful();
        $this->assertSame('vencida', $exit->fresh()->status);
        $this->assertDatabaseHas('work_logs', ['work_id' => $this->work->id, 'action' => 'salida_material_vencida']);
    }

    // ── Retiro en portería ──────────────────────────────────────────────────

    public function test_retire_records_person_plate_time_and_registers_exit(): void
    {
        $exit = $this->approvedExit();
        $person = $this->enterPerson('5005', 'VOL123');

        $this->actingAs($this->guard)->get('/vigilante/exits')
            ->assertInertia(fn (Assert $page) => $page->has('material_exits', 1)->where('material_exits.0.house', 'Casa-02'));

        $this->actingAs($this->guard)
            ->post("/vigilante/material-exits/{$exit->id}/retire", ['entry_id' => $person->id, 'plate' => 'xyz-987'])
            ->assertSessionHasNoErrors();

        $exit->refresh();
        $this->assertSame('ejecutada', $exit->status);
        $this->assertSame($person->id, $exit->entry_id);
        $this->assertSame('XYZ-987', $exit->exit_plate);
        $this->assertSame($this->guard->id, $exit->executed_by);
        $this->assertNotNull($exit->executed_at);

        // La persona sale con el material
        $this->assertNotNull($person->fresh()->exit);
        $this->assertStringContainsString('Retiró material: Escombro de demolición', $person->fresh()->exit->observations);

        Notification::assertSentTo([$this->owner, $this->adminUser], MaterialExitNotification::class, fn ($n) => $n->event === 'retirada');
    }

    public function test_plate_defaults_to_the_entry_plate(): void
    {
        $exit = $this->approvedExit();
        $person = $this->enterPerson('5005', 'VOL123');

        $this->actingAs($this->guard)->post("/vigilante/material-exits/{$exit->id}/retire", ['entry_id' => $person->id]);

        $this->assertSame('VOL123', $exit->fresh()->exit_plate);
    }

    public function test_only_someone_with_an_active_entry_can_retire(): void
    {
        $exit = $this->approvedExit();
        $person = $this->enterPerson('5005');
        $person->exit()->create(['exited_at' => now(), 'exited_by' => 'x']);

        $this->actingAs($this->guard)
            ->post("/vigilante/material-exits/{$exit->id}/retire", ['entry_id' => $person->id])
            ->assertSessionHasErrors('entry_id');

        $this->actingAs($this->guard)
            ->post("/vigilante/material-exits/{$exit->id}/retire", [])
            ->assertSessionHasErrors('entry_id');

        $this->assertSame('aprobada', $exit->fresh()->status);
    }

    public function test_pending_exit_cannot_be_retired(): void
    {
        Notification::fake();
        $this->request($this->guard);
        $exit = MaterialExit::firstOrFail();
        $person = $this->enterPerson('5005');

        $this->actingAs($this->guard)
            ->post("/vigilante/material-exits/{$exit->id}/retire", ['entry_id' => $person->id])
            ->assertSessionHasErrors('material_exit');
    }

    public function test_worker_with_tools_inside_must_use_the_normal_exit(): void
    {
        $exit = $this->approvedExit();
        $worker = $this->work->workers()->create(['first_name' => 'Juan', 'last_name' => 'Obrero', 'cedula' => '9001']);
        WorkItem::create([
            'work_id' => $this->work->id, 'property_id' => $this->house->id, 'work_worker_id' => $worker->id,
            'name' => 'Taladro', 'kind' => 'herramienta', 'quantity_inside' => 1,
        ]);
        $entry = $this->enterPerson('9001');
        $entry->update(['work_worker_id' => $worker->id]);

        $this->actingAs($this->guard)
            ->post("/vigilante/material-exits/{$exit->id}/retire", ['entry_id' => $entry->id])
            ->assertSessionHasErrors('entry_id');

        $this->assertNull($entry->fresh()->exit);
    }

    // ── Canales ─────────────────────────────────────────────────────────────

    public function test_whatsapp_channel_sends_the_approved_template(): void
    {
        config(['services.whatsapp.token' => 'secreto', 'services.whatsapp.phone_number_id' => '999']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]])]);

        $exit = MaterialExit::create([
            'work_id' => $this->work->id, 'description' => 'Escombro', 'quantity' => '3',
            'reason' => 'escombro', 'requested_by' => $this->guard->id,
        ]);

        $this->owner->notifyNow(new MaterialExitNotification($exit->load('work.property', 'requester'), 'solicitada'), [WhatsAppChannel::class]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/999/messages')
            && $request['to'] === '573104445566'
            && $request['template']['name'] === 'salida_material_solicitada'
            && $request->hasHeader('Authorization', 'Bearer secreto'));
    }

    public function test_push_subscriptions_are_routed_to_the_webpush_channel(): void
    {
        PushSubscription::create(['user_id' => $this->owner->id, 'endpoint' => 'https://push.example/a']);

        $this->assertSame(['https://push.example/a'], $this->owner->routeNotificationForWebPush()->pluck('endpoint')->all());
    }

    // ── Campana en la app ───────────────────────────────────────────────────

    public function test_in_app_notifications_can_be_listed_opened_and_marked_read(): void
    {
        $this->request($this->guard); // sin fake: se guarda en la base de datos

        $this->actingAs($this->owner)->get('/works')
            ->assertInertia(fn (Assert $page) => $page->where('notifications_unread', 1));

        $response = $this->actingAs($this->owner)->getJson('/notifications')
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.title', 'Salida de material por aprobar');

        $id = $response->json('items.0.id');
        $this->actingAs($this->owner)->get("/notifications/{$id}/open")
            ->assertRedirect("/works/{$this->work->id}?tab=material");

        $this->assertSame(0, $this->owner->fresh()->unreadNotifications()->count());

        $this->request($this->guard);
        $this->actingAs($this->owner)->postJson('/notifications/read-all')->assertOk();
        $this->assertSame(0, $this->owner->fresh()->unreadNotifications()->count());
    }
}
