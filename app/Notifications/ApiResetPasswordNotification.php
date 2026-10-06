<?php

namespace App\Notifications;

use App\Notifications\Channels\BrevoMailChannel;
use Illuminate\Bus\Queueable;
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
        return [BrevoMailChannel::class];
    }

    public function toBrevo(object $notifiable): array
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');
        $url = $baseUrl.'/reset-password?token='.$this->token.'&email='.urlencode($this->email);
        $expireMinutes = config('auth.passwords.users.expire');

        return [
            'subject' => 'Reinitialisation de votre mot de passe',
            'html' => view('emails.auth.reset-password', [
                'user' => $notifiable,
                'url' => $url,
                'expireMinutes' => $expireMinutes,
            ])->render(),
            'text' => "Reinitialisez votre mot de passe: {$url}\nCe lien expire dans {$expireMinutes} minutes.",
        ];
    }
}
