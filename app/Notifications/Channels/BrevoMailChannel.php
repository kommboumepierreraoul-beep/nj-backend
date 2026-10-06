<?php

namespace App\Notifications\Channels;

use App\Services\BrevoMailService;

class BrevoMailChannel
{
    public function __construct(private readonly BrevoMailService $brevo) {}

    public function send(object $notifiable, object $notification): void
    {
        if (! method_exists($notification, 'toBrevo')) {
            return;
        }

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
    }
}
