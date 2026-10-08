<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envía notificaciones por WhatsApp Cloud API (Meta).
 *
 * Requiere en .env: WHATSAPP_TOKEN, WHATSAPP_PHONE_NUMBER_ID y las plantillas
 * aprobadas en Meta Business. Sin credenciales no envía nada (no falla).
 */
class WhatsAppChannel
{
    public static function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! $to || ! self::isConfigured() || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        /** @var WhatsAppMessage $message */
        $message = $notification->toWhatsApp($notifiable);

        $response = Http::withToken(config('services.whatsapp.token'))
            ->timeout(15)
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/messages',
                config('services.whatsapp.api_version', 'v21.0'),
                config('services.whatsapp.phone_number_id'),
            ), [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $message->template,
                    'language' => ['code' => config('services.whatsapp.language', 'es')],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(
                            fn ($value) => ['type' => 'text', 'text' => (string) $value],
                            $message->parameters,
                        ),
                    ]],
                ],
            ]);

        if ($response->failed()) {
            // No se interrumpe el resto de canales; queda en el log para revisar
            Log::warning('WhatsApp: no se pudo enviar la notificación', [
                'to' => $to,
                'template' => $message->template,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);
        }
    }
}
