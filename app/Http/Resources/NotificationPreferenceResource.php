<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Represente une preference RESOLUE (Doc/notifications_modele_donnees.md, §3.3) : soit la
// ligne enregistree, soit les valeurs par defaut de la categorie si l'utilisateur n'a jamais
// personnalise — voir NotificationController::preferences(), qui construit ce tableau associatif
// pour les 6 categories dans tous les cas, meme sans aucune ligne en base.
class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'category' => $this['category'],
            'email_enabled' => $this['email'],
        ];
    }
}
