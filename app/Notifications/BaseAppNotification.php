<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Notification as AppNotificationModel;
use App\Models\NotificationPreference;
use App\Notifications\Channels\AppBrevoMailChannel;
use App\Notifications\Channels\AppDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Throwable;

// Classe de base des 7 notifications internes du module (Doc/notifications_modele_donnees.md,
// §6). Porte la logique partagée (résolution des canaux à partir des préférences utilisateur,
// gabarit générique pour chaque canal, traçage best-effort de channels_sent) —
// chaque sous-classe ne définit que type()/category()/priority()/title()/body(), et
// éventuellement data()/relatedEntityType()/relatedEntityId() si elle a un contexte à
// transporter (voir §3.2 pour le rôle de ces deux derniers champs).
abstract class BaseAppNotification extends Notification
{
    use Queueable;

    private ?AppNotificationModel $persisted = null;

    /**
     * Code court et stable de l'événement (ex. "sales_order.status_changed"), stocké dans
     * notifications.type — jamais le nom de classe PHP complet (Doc/notifications_modele_donnees.md, §3.2).
     */
    abstract public function type(): string;

    abstract public function category(): NotificationCategory;

    abstract public function priority(): NotificationPriority;

    abstract public function title(object $notifiable): string;

    abstract public function body(object $notifiable): string;

    /**
     * Payload contextuel (identifiants liés, lien frontend suggéré...) — vide par défaut.
     *
     * @return array<string, mixed>
     */
    public function data(object $notifiable): array
    {
        return [];
    }

    public function relatedEntityType(): ?string
    {
        return null;
    }

    public function relatedEntityId(): int|string|null
    {
        return null;
    }

    /**
     * Résolution des canaux (Doc/notifications_modele_donnees.md, §2, décisions 3/4) :
     * IN_APP toujours présent en premier (jamais désactivable), EMAIL selon la préférence de
     * la catégorie. Pas d'autre canal dans ce lot (décision §0.2 révisée le 2026-08-27 : SMS/
     * WhatsApp retirés du périmètre).
     */
    public function via(object $notifiable): array
    {
        $preferences = NotificationPreference::resolveFor($notifiable, $this->category());
        $channels = [AppDatabaseChannel::class];

        if ($preferences['email']) {
            $channels[] = AppBrevoMailChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApp(object $notifiable): array
    {
        return [
            'notifiable_user_id' => $notifiable->id,
            'type' => $this->type(),
            'category' => $this->category()->value,
            'priority' => $this->priority()->value,
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'data' => $this->data($notifiable),
            'related_entity_type' => $this->relatedEntityType(),
            'related_entity_id' => $this->relatedEntityId(),
        ];
    }

    /**
     * Gabarit email générique à partir de title()/body() — suffisant pour ce lot (pas de
     * template HTML dédié par événement, contrairement à reset-password/invitation qui ont
     * un vrai parcours utilisateur). Une sous-classe peut le surcharger si un événement a
     * besoin d'un rendu plus riche.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public function toBrevo(object $notifiable): array
    {
        return [
            'subject' => $this->title($notifiable),
            'html' => '<p>'.e($this->body($notifiable)).'</p>',
            'text' => $this->body($notifiable),
        ];
    }

    /**
     * Appelé par AppDatabaseChannel juste après la création de la ligne `notifications` :
     * permet aux canaux Brevo suivants (même instance de notification, voir via()) de tracer
     * leur résultat sur cette ligne via recordChannelResult().
     */
    public function attachPersistedModel(AppNotificationModel $model): void
    {
        $this->persisted = $model;
    }

    /**
     * Trace best-effort du résultat d'un canal dans notifications.channels_sent (Doc/notifications_modele_donnees.md,
     * §3.2/§6) — ne doit jamais faire échouer l'envoi pour un problème de traçage.
     */
    public function recordChannelResult(string $channel, bool $success): void
    {
        if (! $this->persisted) {
            return;
        }

        try {
            $channelsSent = $this->persisted->channels_sent ?? [];
            $channelsSent[$channel] = $success;
            $this->persisted->update(['channels_sent' => $channelsSent]);
        } catch (Throwable) {
            // Best-effort : ne jamais bloquer l'envoi pour un probleme de tracage.
        }
    }
}
