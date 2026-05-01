<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id',
    'approval_status',
    'submitted_at',
    'reviewed_at',
    'reviewed_by',
    'review_notes',
    'approved_at',
    'rejected_at',
    'content_status',
    'visibility',
    'name',
    'tagline',
    'description',
    'website_url',
    'logo_url',
    'industry',
    'stage',
    'location',
    'founders',
    'submitter_role',
    'linkedin_url',
])]
class StartupListing extends Model
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(StartupListingRevision::class);
    }

    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
            'content_status' => ContentStatus::class,
            'visibility' => ContentVisibility::class,
            'founders' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
