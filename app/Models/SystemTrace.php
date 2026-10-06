<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

#[Fillable(['user_id', 'event', 'email_attempted', 'ip_address', 'user_agent', 'route', 'http_method', 'context_json'])]
class SystemTrace extends Model
{
    // Journal immuable : pas de colonne updated_at (meme convention que AuditLog).
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enregistre un evenement technique/de securite (connexion, deconnexion,
     * jeton invalide, permission refusee...) — volet "trace systeme", distinct
     * du journal d'audit metier (AuditLog). Best-effort, comme AuditLog::record() :
     * une erreur de journalisation ne doit jamais faire echouer la requete HTTP
     * qu'elle documente. Le contexte (IP, user-agent, route, methode) est
     * capture automatiquement depuis la requete courante (Doc/audit_trace_systeme.md,
     * section 3.4).
     */
    public static function record(string $event, ?User $user = null, ?string $emailAttempted = null, ?array $context = null): void
    {
        try {
            $request = request();

            static::query()->create([
                'user_id' => $user?->id,
                'event' => $event,
                'email_attempted' => $emailAttempted,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'route' => $request?->path(),
                'http_method' => $request?->method(),
                'context_json' => $context,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Journalisation best-effort : ne jamais bloquer la requete.
        }
    }
}
