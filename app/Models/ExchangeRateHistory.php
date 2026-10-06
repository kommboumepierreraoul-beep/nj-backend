<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['currency_id', 'rate_to_xaf', 'effective_date'])]
class ExchangeRateHistory extends Model
{
    // Le pluriel Eloquent par defaut ("exchange_rate_histories") ne correspond pas
    // au nom de la table cree par la migration (exchange_rate_history) : on le precise.
    protected $table = 'exchange_rate_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'rate_to_xaf' => 'decimal:6',
            'effective_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
