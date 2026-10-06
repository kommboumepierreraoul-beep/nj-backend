<?php

namespace App\Http\Resources;

use App\Models\ExchangeRateHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Dernier taux connu (<= aujourd'hui) — utile pour l'écran Paramètres -> Entreprise
        // -> Devises, qui affiche la parité courante à côté de chaque devise. Chargé à la
        // demande (une requête légère par devise, la liste des devises restant très courte).
        $latestRate = ExchangeRateHistory::query()
            ->where('currency_id', $this->id)
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')
            ->first();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'latest_rate_to_xaf' => $latestRate ? (string) $latestRate->rate_to_xaf : null,
            'latest_rate_effective_date' => $latestRate?->effective_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
