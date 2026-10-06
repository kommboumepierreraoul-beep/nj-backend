<?php

namespace App\Notifications\Channels;

use App\Services\BrevoWhatsAppService;
use Throwable;

// Canal WhatsApp best-effort du module Notifications (Doc/notifications_modele_donnees.md,
// §4/§6) : ne fonctionnera pas tant que le template WhatsApp Business n'est pas approuve par
// Meta/configure cote Brevo (BrevoWhatsAppService leve une exception explicite dans ce cas,
// interceptee ici et tracee dans channels_sent plutot que de remonter — best-effort).
class BrevoWhatsAppChannel
{
    public function __construct(private readonly BrevoWhatsAppService $brevo) {}

    public function send(object $notifiable, object $notification): void
    {
        if (! method_exists($notification, 'toBrevoWhatsApp')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('whatsapp') ?? $notifiable->phone ?? null;

        if (! $phone) {
            return;
        }

        $success = true;

        try {
            $this->brevo->send($phone, $notification->toBrevoWhatsApp($notifiable));
        } catch (Throwable) {
            $success = false;
        }

        if (method_exists($notification, 'recordChannelResult')) {
            $notification->recordChannelResult('whatsapp', $success);
        }
    }
}
