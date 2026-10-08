<?php

namespace App\Notifications\Channels;

/**
 * Mensaje de WhatsApp basado en una plantilla aprobada en Meta.
 * WhatsApp Cloud API solo permite iniciar conversaciones con plantillas;
 * los parámetros reemplazan {{1}}, {{2}}… del cuerpo de la plantilla.
 */
class WhatsAppMessage
{
    /** @param  array<int, string>  $parameters */
    public function __construct(
        public string $template,
        public array $parameters = [],
    ) {}
}
