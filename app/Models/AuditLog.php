<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

#[Fillable(['actor_user_id', 'action', 'entity_type', 'entity_id', 'old_value_json', 'new_value_json', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    // Journal immuable : pas de colonne updated_at.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_value_json' => 'array',
            'new_value_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Enregistre une entree de journal d'audit pour une action sensible sur
     * une entite metier (utilisateur, produit, fournisseur, client...).
     * Best-effort : une erreur de journalisation ne doit jamais faire echouer
     * l'action metier qu'elle documente. Le contexte de la requete (IP,
     * user-agent) est capture automatiquement, sans avoir a le passer
     * explicitement depuis chaque contoleur (Doc/audit_trace_systeme.md,
     * section 3.3).
     */
    public static function record(string $action, Model $entity, ?User $actor, ?array $old = null, ?array $new = null): void
    {
        try {
            static::query()->create([
                'actor_user_id' => $actor?->id,
                'action' => $action,
                'entity_type' => class_basename($entity),
                'entity_id' => $entity->getKey(),
                'old_value_json' => $old,
                'new_value_json' => $new,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Journalisation best-effort : ne jamais bloquer l'action metier.
        }
    }
}
