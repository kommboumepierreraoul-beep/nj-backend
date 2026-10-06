<?php

namespace App\Notifications\Channels;

use App\Services\BrevoMailService;
use Throwable;

// Canal email best-effort du module Notifications (Doc/notifications_modele_donnees.md, §1/§6).
// Encapsule BrevoMailService (le meme service que BrevoMailChannel, deja utilise pour reset
// password/invitation) dans un try/catch : un echec Brevo ne doit jamais bloquer les autres
// canaux ni l'action metier qui a declenche la notification. Volontairement distinct de
// App\Notifications\Channels\BrevoMailChannel (non modifie) dont le comportement — laisser
// remonter l'exception — reste voulu pour reset password/invitation (seul canal disponible,
// un echec doit y etre visible).
class AppBrevoMailChannel
{
    public function __construct(private readonly BrevoMailService $brevo) {}

    public function send(object $notifiable, object $notification): void
    {
        if (! method_exists($notification, 'toBrevo')) {
            return;
        }

        $success = true;

        try {
            $message = $notification->toBrevo($notifiable);

            $this->brevo->send(
                to: [[
                    'email' => $notifiable->routeNotificationFor('mail') ?? $notifiable->email,
                    'name' => $notifiable->full_name ?? $notifiable->name ?? null,
                ]],
                subject: $message['subject'],
                htmlContent: $message['html'],
                textContent: $message['text'] ?? null,
            );
        } catch (Throwable) {
            $success = false;
        }

        if (method_exists($notification, 'recordChannelResult')) {
            $notification->recordChannelResult('email', $success);
        }
    }
}
