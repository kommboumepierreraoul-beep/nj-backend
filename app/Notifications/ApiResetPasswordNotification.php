<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $email,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');
        $url = $baseUrl.'/reset-password?token='.$this->token.'&email='.urlencode($this->email);

        return (new MailMessage)
            ->subject('Reinitialisation de votre mot de passe')
            ->line('Vous recevez cet email car une demande de reinitialisation de mot de passe a ete effectuee.')
            ->action('Reinitialiser le mot de passe', $url)
            ->line('Ce lien expire dans '.config('auth.passwords.users.expire').' minutes.')
            ->line('Si vous n avez pas fait cette demande, ignorez cet email.');
    }
}
