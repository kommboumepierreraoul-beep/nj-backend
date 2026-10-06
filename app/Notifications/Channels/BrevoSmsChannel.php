<?php

namespace App\Notifications\Channels;

use App\Services\BrevoSmsService;
use Throwable;

// Canal SMS best-effort du module Notifications (Doc/notifications_modele_donnees.md, §4/§6) :
// n'envoie que si le destinataire a un telephone renseigne (verifie aussi en amont dans
// BaseAppNotification::via(), verification repetee ici par defense en profondeur). Necessite
// un credit SMS actif sur le compte Brevo — prerequis operationnel non couvert par ce code.
class BrevoSmsChannel
{
    public function __construct(private readonly BrevoSmsService $brevo) {}

    public function send(object $notifiable, object $notification): void
    {
        if (! method_exists($notification, 'toBrevoSms')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('sms') ?? $notifiable->phone ?? null;

        if (! $phone) {
            return;
        }

        $success = true;

        try {
            $this->brevo->send($phone, $notification->toBrevoSms($notifiable));
        } catch (Throwable) {
            $success = false;
        }

        if (method_exists($notification, 'recordChannelResult')) {
            $notification->recordChannelResult('sms', $success);
        }
    }
}
