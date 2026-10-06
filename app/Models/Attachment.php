<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['attachable_type', 'attachable_id', 'file_name', 'file_path', 'mime_type', 'size_kb', 'is_primary', 'sort_order', 'uploaded_by_user_id', 'uploaded_at'])]
class Attachment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'uploaded_at' => 'datetime',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function mediaTypes(): HasMany
    {
        return $this->hasMany(AttachmentMediaType::class);
    }

    public function hasMediaType(AttachmentType $type): bool
    {
        return $this->mediaTypes()->where('type', $type->value)->exists();
    }
}
