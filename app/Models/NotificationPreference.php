<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'category', 'email_enabled'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'email_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Préférence effective d'un utilisateur pour une catégorie (Doc/notifications_modele_donnees.md,
     * §3.3) : la ligne enregistrée si elle existe, sinon les valeurs par défaut de la catégorie
     * (NotificationCategory::defaultChannels()) sans créer de ligne — même pattern que
     * FlowStageThreshold::resolveFor(), mais avec un repli en mémoire plutôt qu'un "aucun seuil".
     *
     * @return array{email: bool}
     */
    public static function resolveFor(User $user, NotificationCategory $category): array
    {
        $preference = static::query()
            ->where('user_id', $user->id)
            ->where('category', $category->value)
            ->first();

        if (! $preference) {
            return $category->defaultChannels();
        }

        return [
            'email' => $preference->email_enabled,
        ];
    }
}
