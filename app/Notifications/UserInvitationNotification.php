<?php

namespace App\Notifications;

use App\Notifications\Channels\BrevoMailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        private readonly string $platformUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return [BrevoMailChannel::class];
    }

    public function toBrevo(object $notifiable): array
    {
        $url = rtrim($this->platformUrl, '/')
            .'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->email);
        $expireMinutes = config('auth.passwords.users.expire');

        return [
            'subject' => 'Votre acces NJ Global Trade',
            'html' => view('emails.auth.invitation', [
                'user' => $notifiable,
                'url' => $url,
                'expireMinutes' => $expireMinutes,
            ])->render(),
            'text' => "Votre compte NJ Global Trade est pret.\nEmail: {$notifiable->email}\nDefinissez votre mot de passe: {$url}\nCe lien expire dans {$expireMinutes} minutes.",
        ];
    }
}
