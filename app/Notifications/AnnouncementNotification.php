<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Reintentos si falla el envío */
    public int $tries = 3;

    /** Tiempo de espera entre reintentos (segundos) */
    public int $backoff = 10;

    public function __construct(public Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Push al celular solo si el comunicado lo pide y el usuario activó las notificaciones
        if ($this->announcement->send_push
            && filled(config('webpush.vapid.public_key'))
            && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'url' => '/announcements',
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->announcement->title)
            ->body(str($this->announcement->body)->limit(140)->toString())
            ->data(['url' => '/announcements']);
    }
}
