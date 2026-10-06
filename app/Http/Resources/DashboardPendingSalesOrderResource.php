<?php

namespace App\Http\Resources;

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Vue "facture en attente" du tableau de bord (cahier des charges section 2.3 + signal de
 * relance section 2.4) : reprend les champs utiles de SalesOrder et ajoute le niveau
 * d'alerte calcule a partir de valid_until, sans dupliquer SalesOrderResource (deja complet
 * mais sans cette semantique specifique au dashboard).
 */
class DashboardPendingSalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $validUntil = $this->valid_until ? Carbon::parse($this->valid_until) : null;
        $joursRestants = $validUntil ? (int) Carbon::today()->diffInDays($validUntil, false) : null;

        // "DEPASSEE" si l'echeance est deja passee, "PROCHE" si elle tombe dans les
        // DashboardController::RELANCE_PROCHE_SEUIL_JOURS jours a venir (section 2.4 du
        // cahier des charges), sinon aucune alerte — meme seuil que
        // DashboardController::pendingSummary(), pour ne jamais diverger du decompte agrege.
        $niveauAlerte = match (true) {
            $validUntil === null => null,
            $joursRestants < 0 => 'DEPASSEE',
            $joursRestants <= DashboardController::RELANCE_PROCHE_SEUIL_JOURS => 'PROCHE',
            default => null,
        };

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status?->value ?? $this->status,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'total_amount' => $this->total_amount,
            'order_date' => $this->order_date,
            'valid_until' => $this->valid_until,
            'jours_restants' => $joursRestants,
            'niveau_alerte' => $niveauAlerte,
            // Pas de whenLoaded() ici : contrairement a SalesOrderResource (qui delegue a
            // ClientResource, capable de gerer nativement l'absence de relation chargee), ce
            // tableau est construit a la main — un whenLoaded() imbrique dans un tableau
            // manuel laisserait fuiter un sentinel MissingValue au lieu de l'omettre
            // proprement. DashboardController::pendingSalesOrders() charge toujours
            // 'client.category' et 'currency', donc un acces direct est sans risque ici.
            'client' => $this->client ? [
                'id' => $this->client->id,
                'full_name' => $this->client->full_name,
                'category' => $this->client->category ? [
                    'code' => $this->client->category->code,
                    'label' => $this->client->category->label,
                ] : null,
            ] : null,
            'currency' => $this->currency,
        ];
    }
}
