<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Envoi d'un message WhatsApp via l'API Brevo (Doc/communication_whatsapp_manuelle.md, §5).
// Contrairement au module Notifications (interne, best-effort), ce service sert des envois
// manuels initiés par un utilisateur vers un client/fournisseur : un échec doit remonter
// à l'appelant (pas de try/catch ici), même principe que BrevoMailChannel pour reset
// password/invitation — c'est à App\Support\WhatsAppDocumentSender et aux contrôleurs
// appelants de décider comment traduire l'exception en réponse HTTP.
class BrevoWhatsAppService
{
    private const SEND_WHATSAPP_URL = 'https://api.brevo.com/v3/whatsapp/sendMessage';

    /**
     * @param  array<string, string>  $templateParams  Paramètres injectés dans le template
     *                                                  Meta approuvé (ex. "1" => valeur).
     */
    public function send(string $toPhone, string $senderNumber, int $templateId, array $templateParams = []): void
    {
        $apiKey = config('services.brevo.api_key');

        if (! $apiKey) {
            throw new RuntimeException('La cle API Brevo est manquante.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['api-key' => $apiKey])
            ->timeout(10)
            ->post(self::SEND_WHATSAPP_URL, [
                'templateId' => $templateId,
                'senderNumber' => $senderNumber,
                'contactNumbers' => [$toPhone],
                'params' => $templateParams,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Brevo a refuse l envoi WhatsApp: '.$response->body());
        }
    }
}
