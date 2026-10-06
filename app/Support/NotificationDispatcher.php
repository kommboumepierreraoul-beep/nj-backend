<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

// Point d'entree unique pour declencher une notification interne depuis un controleur
// existant (Doc/notifications_modele_donnees.md, §6/§8) : centralise (1) la resolution des
// destinataires "createur de l'entite, ou repli sur les ADMIN/SUPER_ADMIN actifs si nul"
// (decision §5, "Repli si le destinataire relationnel est null") et (2) l'enveloppe
// try/catch — l'envoi d'une notification ne doit jamais faire echouer l'action metier qui
// la declenche, meme si NotificationPreference::resolveFor() ou l'envoi lui-meme leve une
// exception inattendue (les canaux eux-memes sont deja best-effort, voir BaseAppNotification,
// mais cette couche est un filet de securite supplementaire, meme esprit que AuditLog::record()).
class NotificationDispatcher
{
    /**
     * Notifie l'utilisateur donne, ou tous les ADMIN/SUPER_ADMIN actifs si $creator est null
     * (entite sans createur connu, ex. created_by_user_id/requested_by_user_id nullable en base).
     */
    public static function notifyCreatorOrAdmins(?User $creator, BaseNotification $notification): void
    {
        static::safeSend($creator ? collect([$creator]) : static::activeAdmins(), $notification);
    }

    public static function notifyAdmins(BaseNotification $notification): void
    {
        static::safeSend(static::activeAdmins(), $notification);
    }

    /**
     * Point d'entree generique (utilise par App\Console\Commands\ScanNotificationAlerts quand
     * les deux replis ci-dessus ne suffisent pas, ex. "le createur ET tous les ADMIN si
     * DEPASSEE" — la deduplication par id revient a l'appelant, voir dedupRecipients()).
     */
    public static function notify(iterable $recipients, BaseNotification $notification): void
    {
        static::safeSend($recipients, $notification);
    }

    public static function activeAdmins(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value])
            ->get();
    }

    /**
     * Fusionne plusieurs listes de destinataires potentiellement chevauchantes (ex. le
     * createur d'une commande ET les ADMIN, qui peuvent se recouper) en une seule liste unique
     * par id, sans valeur nulle — evite d'envoyer deux fois la meme notification a la meme
     * personne.
     */
    public static function dedupRecipients(mixed ...$groups): Collection
    {
        return collect($groups)
            ->flatten()
            ->filter()
            ->unique('id')
            ->values();
    }

    private static function safeSend(iterable $recipients, BaseNotification $notification): void
    {
        try {
            NotificationFacade::send($recipients, $notification);
        } catch (Throwable) {
            // Best-effort : ne jamais bloquer l'action metier declenchante.
        }
    }
}
