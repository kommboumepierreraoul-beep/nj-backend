<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Envoi de SMS transactionnel via l'API Brevo (même clé API que BrevoMailService, voir
// Doc/notifications_modele_donnees.md, §6). Prérequis opérationnel non couvert par ce code :
// crédit SMS activé sur le compte Brevo et, selon le pays du destinataire, un sender ID
// alphanumérique pré-validé (§4 du cadrage) — sans quoi Brevo refuse l'envoi côté API.
class BrevoSmsService
{
    private const SEND_SMS_URL = 'https://api.brevo.com/v3/transactionalSMS/sms';

    public function send(string $toPhone, string $content): void
    {
        $apiKey = config('services.brevo.api_key');

        if (! $apiKey) {
            throw new RuntimeException('La cle API Brevo est manquante.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['api-key' => $apiKey])
            ->timeout(10)
            ->post(self::SEND_SMS_URL, [
                'sender' => config('services.brevo.sms_sender'),
                'recipient' => $toPhone,
                'content' => $content,
                'type' => 'transactional',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Brevo a refuse l envoi du SMS: '.$response->body());
        }
    }
}
