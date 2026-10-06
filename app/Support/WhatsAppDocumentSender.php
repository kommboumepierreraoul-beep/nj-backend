<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Supplier;
use App\Services\BrevoWhatsAppService;

// Point d'entrée unique pour les envois manuels de documents/relances par WhatsApp
// (Doc/communication_whatsapp_manuelle.md). Volontairement distinct de
// App\Support\NotificationDispatcher (module Notifications, interne, best-effort) : ici
// l'envoi est déclenché par un clic explicite d'un utilisateur vers un tiers externe
// (client/fournisseur), donc chaque contrôleur appelant doit vérifier les 3 pré-requis
// avant d'appeler send() (voir templateIdFor()/clientWhatsAppNumber()/companyWhatsAppNumber())
// plutôt que de laisser échouer silencieusement ou de deviner une valeur.
class WhatsAppDocumentSender
{
    /**
     * Id du template Meta approuvé pour cet événement (Doc/communication_whatsapp_manuelle.md,
     * §3/§4), ou null si non encore configuré côté compte Brevo — dans ce cas l'appelant doit
     * refuser l'envoi en 422, jamais tenter un appel Brevo qui échouerait de toute façon.
     */
    public static function templateIdFor(string $eventKey): ?int
    {
        $id = config("services.brevo.whatsapp_templates.{$eventKey}");

        return $id ? (int) $id : null;
    }

    /**
     * Numéro WhatsApp du client (Doc/communication_whatsapp_manuelle.md, §3.1) : le contact
     * préféré s'il est de type WHATSAPP, sinon le premier contact WHATSAPP trouvé — Client
     * n'a pas de colonne whatsapp propre (contrairement à Supplier), les coordonnées passent
     * par client_contacts/contact_channel_types (module Clients, déjà en place).
     */
    public static function clientWhatsAppNumber(Client $client): ?string
    {
        $contact = $client->contacts()
            ->whereHas('channelType', fn ($query) => $query->where('code', 'WHATSAPP'))
            ->orderByDesc('is_preferred')
            ->first();

        return $contact?->value;
    }

    /**
     * Numéro WhatsApp du fournisseur : colonne suppliers.whatsapp, déjà existante
     * (module Produits/Fournisseurs), rien à ajouter.
     */
    public static function supplierWhatsAppNumber(Supplier $supplier): ?string
    {
        return $supplier->whatsapp;
    }

    /**
     * Numéro expéditeur (le compte WhatsApp Business de NJ Global Trade lui-même) :
     * company_settings.whatsapp, déjà utilisé par le module Factures/Proforma pour les
     * mêmes coordonnées d'entreprise — pas de variable d'environnement dédiée.
     */
    public static function companyWhatsAppNumber(): ?string
    {
        return CompanySettings::current()->whatsapp;
    }

    /**
     * @param  array<string, string>  $templateParams
     *
     * @throws \RuntimeException si l'appel Brevo échoue — volontairement non capturé ici
     *                           (voir BrevoWhatsAppService), à charge du contrôleur appelant
     *                           de retourner une réponse HTTP claire (502) plutôt que
     *                           d'échouer silencieusement comme le ferait un canal du module
     *                           Notifications.
     */
    public static function send(string $toPhone, string $senderNumber, int $templateId, array $templateParams): void
    {
        app(BrevoWhatsAppService::class)->send($toPhone, $senderNumber, $templateId, $templateParams);
    }
}
