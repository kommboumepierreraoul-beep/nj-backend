<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attachment_id', 'type'])]
class AttachmentMediaType extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected function casts(): array
    {
        return ['type' => AttachmentType::class];
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
