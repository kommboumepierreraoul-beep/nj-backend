<?php

namespace App\Notifications\Channels;

use App\Models\Notification as AppNotificationModel;
use Throwable;

// Canal in-app du module Notifications (Doc/notifications_modele_donnees.md, §6) : écrit une
// ligne dans la table `notifications` (App\Models\Notification) plutôt que d'appeler une API
// externe. Toujours en tête de la liste retournée par BaseAppNotification::via() : les canaux
// Brevo suivants s'appuient sur attachPersistedModel() pour tracer leur résultat.
class AppDatabaseChannel
{
    public function send(object $notifiable, object $notification): void
    {
        if (! method_exists($notification, 'toApp')) {
            return;
        }

        try {
            $model = AppNotificationModel::query()->create($notification->toApp($notifiable));

            if (method_exists($notification, 'attachPersistedModel')) {
                $notification->attachPersistedModel($model);
            }
        } catch (Throwable) {
            // Best-effort (Doc/notifications_modele_donnees.md, §6) : ne jamais bloquer
            // l'action metier ni les autres canaux si l'ecriture in-app echoue.
        }
    }
}
