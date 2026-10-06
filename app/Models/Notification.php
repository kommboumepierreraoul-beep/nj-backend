<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Notification interne persistée (canal in-app, Doc/notifications_modele_donnees.md, §3.2).
// Nommage volontairement `App\Models\Notification`, distinct de la classe de base
// `Illuminate\Notifications\Notification` (namespace différent) utilisée par les 7 classes
// d'évènement de app/Notifications/ — ne jamais importer les deux sans alias dans un même
// fichier (voir App\Notifications\Channels\AppDatabaseChannel).
#[Fillable(['notifiable_user_id', 'type', 'category', 'priority', 'title', 'body', 'data', 'related_entity_type', 'related_entity_id', 'channels_sent'])]
class Notification extends Model
{
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'priority' => NotificationPriority::class,
            'data' => 'array',
            'channels_sent' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function notifiable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notifiable_user_id');
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }
}
