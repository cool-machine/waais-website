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
    'cancelled_at',
    'cancellation_note',
    'recap_content',
    'reminder_days_before',
    'title',
    'summary',
    'description',
    'starts_at',
    'ends_at',
    'location',
    'format',
    'image_url',
    'registration_url',
    'capacity_limit',
    'waitlist_open',
])]
class Event extends Model
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Derived label for UI rendering. The frontend uses this to label
     * the eyebrow/meta on event cards. Order of precedence:
     *   - cancelled (only ever visible to admins; the public projection
     *     filters cancelled events out)
     *   - recap (event is in the past AND has recap_content)
     *   - past (event is in the past AND has no recap content)
     *   - upcoming (default)
     */
    public function derivedStatus(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }

        $isPast = $this->starts_at !== null && $this->starts_at->isPast();
        if ($isPast) {
            return filled($this->recap_content) ? 'recap' : 'past';
        }

        return 'upcoming';
    }

    protected function casts(): array
    {
        return [
            'content_status' => ContentStatus::class,
            'visibility' => ContentVisibility::class,
            'published_at' => 'datetime',
            'hidden_at' => 'datetime',
            'archived_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_days_before' => 'integer',
            'capacity_limit' => 'integer',
            'waitlist_open' => 'boolean',
        ];
    }
}
