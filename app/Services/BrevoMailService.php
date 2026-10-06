<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoMailService
{
    private const SEND_EMAIL_URL = 'https://api.brevo.com/v3/smtp/email';

    /**
     * Envoie un email transactionnel via l'API Brevo.
     *
     * @param  array<int, array{email: string, name?: string|null}>  $to
     */
    public function send(
        array $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
    ): void {
        $apiKey = config('services.brevo.api_key');

        if (! $apiKey) {
            throw new RuntimeException('La cle API Brevo est manquante.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['api-key' => $apiKey])
            ->timeout(10)
            ->post(self::SEND_EMAIL_URL, [
                'sender' => [
                    'email' => config('services.brevo.sender_email'),
                    'name' => config('services.brevo.sender_name'),
                ],
                'to' => $to,
                'subject' => $subject,
                'htmlContent' => $htmlContent,
                'textContent' => $textContent,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Brevo a refuse l envoi du mail: '.$response->body());
        }
    }
}
