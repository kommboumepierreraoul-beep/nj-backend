<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $temporaryPassword,
        private readonly string $platformUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre acces NJ Global Trade')
            ->greeting('Bonjour '.$notifiable->full_name)
            ->line('Un compte a ete cree pour vous sur la plateforme NJ Global Trade.')
            ->line('Email: '.$notifiable->email)
            ->line('Role: '.($notifiable->role?->value ?? $notifiable->role))
            ->line('Mot de passe temporaire: '.$this->temporaryPassword)
            ->action('Acceder a la plateforme', rtrim($this->platformUrl, '/'))
            ->line('Pour votre securite, vous devrez changer ce mot de passe apres votre premiere connexion.');
    }
}
