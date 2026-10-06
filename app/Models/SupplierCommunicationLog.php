<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'channel', 'direction', 'subject', 'summary', 'attachment_id', 'logged_by_user_id', 'occurred_at'])]
class SupplierCommunicationLog extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'direction' => CommunicationDirection::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
