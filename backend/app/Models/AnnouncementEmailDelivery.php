<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'announcement_id',
    'user_id',
    'announcement_published_at',
    'sent_at',
])]
class AnnouncementEmailDelivery extends Model
{
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'announcement_published_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
