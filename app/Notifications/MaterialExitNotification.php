<?php

namespace App\Notifications;

use App\Models\MaterialExit;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Channels\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Avisos de salidas de material de una obra.
 *
 * - solicitada: al propietario y al administrador (app, push, correo y WhatsApp)
 * - aprobada:   a los vigilantes y a quien la pidió (app y push)
 * - rechazada:  a quien la pidió (app y push)
 * - retirada:   al propietario y al administrador (app, push, correo y WhatsApp)
 */
class MaterialExitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public const EVENTS = ['solicitada', 'aprobada', 'rechazada', 'retirada'];

    public function __construct(public MaterialExit $exit, public string $event) {}

    /** Correo y WhatsApp solo para lo que necesita la atención del propietario o del admin */
    private function isExternal(): bool
    {
        return in_array($this->event, ['solicitada', 'retirada'], true);
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled(config('webpush.vapid.public_key')) && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        if ($this->isExternal() && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        if ($this->isExternal() && WhatsAppChannel::isConfigured() && $notifiable->routeNotificationFor('whatsapp')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    // ── Contenido ───────────────────────────────────────────────────────────

    private function house(): string
    {
        return $this->exit->work->property->full_label;
    }

    private function item(): string
    {
        return "{$this->exit->description} ({$this->exit->quantity})";
    }

    private function retiredBy(): string
    {
        $entry = $this->exit->entry;

        return $entry ? "{$entry->full_name} · CC {$entry->cedula}" : '—';
    }

    public function title(): string
    {
        return match ($this->event) {
            'solicitada' => 'Salida de material por aprobar',
            'aprobada' => 'Salida de material aprobada',
            'rechazada' => 'Salida de material rechazada',
            'retirada' => 'Material retirado',
        };
    }

    public function body(): string
    {
        return match ($this->event) {
            'solicitada' => "{$this->item()} en {$this->house()}. Solicitó {$this->exit->requester?->full_name}.",
            'aprobada' => "{$this->item()} en {$this->house()}. Válida hasta {$this->exit->expires_at?->format('d/m/Y H:i')}.",
            'rechazada' => "{$this->item()} en {$this->house()}. Rechazó {$this->exit->approver?->full_name}.",
            'retirada' => "{$this->item()} salió de {$this->house()} con {$this->retiredBy()}"
                .($this->exit->exit_plate ? ", placa {$this->exit->exit_plate}" : '')
                ." el {$this->exit->executed_at?->format('d/m/Y H:i')}.",
        };
    }

    public function url(): string
    {
        return "/works/{$this->exit->work_id}?tab=material";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'material_exit',
            'event' => $this->event,
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'material_exit_id' => $this->exit->id,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->data(['url' => $this->url()]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title().' — '.$this->house())
            ->greeting("Hola {$notifiable->first_name}")
            ->line($this->body());

        return $this->event === 'solicitada'
            ? $mail->line('Revisa la solicitud y apruébala o recházala. La aprobación es válida por 48 horas.')
                ->action('Revisar solicitud', url($this->url()))
            : $mail->action('Ver obra', url($this->url()));
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $templates = config('services.whatsapp.templates');

        return $this->event === 'solicitada'
            ? new WhatsAppMessage($templates['material_exit_requested'], [
                $notifiable->first_name,
                $this->item(),
                $this->house(),
                (string) $this->exit->requester?->full_name,
            ])
            : new WhatsAppMessage($templates['material_exit_retired'], [
                $notifiable->first_name,
                $this->item(),
                $this->house(),
                $this->retiredBy().($this->exit->exit_plate ? " · placa {$this->exit->exit_plate}" : ''),
                (string) $this->exit->executed_at?->format('d/m/Y H:i'),
            ]);
    }
}
