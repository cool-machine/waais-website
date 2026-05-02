<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'created_by',
    'content_status',
    'visibility',
    'published_at',
    'hidden_at',
    'archived_at',
    'audience',
    'channel',
    'title',
    'summary',
    'body',
    'action_label',
    'action_url',
])]
class Announcement extends Model
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'content_status' => ContentStatus::class,
            'visibility' => ContentVisibility::class,
            'published_at' => 'datetime',
            'hidden_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
