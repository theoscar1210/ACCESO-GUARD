<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

/**
 * Suscripción de un navegador a las notificaciones push. Extiende el modelo del
 * paquete webpush para que su canal pueda enviar y limpiar las suscripciones vencidas.
 */
class PushSubscription extends WebPushSubscription
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
