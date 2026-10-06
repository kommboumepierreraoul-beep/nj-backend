<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'channel_type_id', 'value', 'label', 'is_preferred'])]
class ClientContact extends Model
{
    protected function casts(): array
    {
        return ['is_preferred' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function channelType(): BelongsTo
    {
        return $this->belongsTo(ContactChannelType::class, 'channel_type_id');
    }
}
