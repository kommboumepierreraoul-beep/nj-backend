<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'symbol', 'is_default', 'is_active'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function exchangeRateHistory(): HasMany
    {
        return $this->hasMany(ExchangeRateHistory::class);
    }

    public function preferredByClients(): HasMany
    {
        return $this->hasMany(Client::class, 'preferred_currency_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function salesOrderPayments(): HasMany
    {
        return $this->hasMany(SalesOrderPayment::class);
    }

    // Ajout module "factures/proforma" (Doc/factures_modele_donnees.md, section 7.1).
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
