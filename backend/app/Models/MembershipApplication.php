<?php

namespace App\Models;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'applicant_id',
    'approval_status',
    'affiliation_type',
    'email',
    'first_name',
    'last_name',
    'phone_whatsapp',
    'is_alumnus',
    'school_affiliation',
    'graduation_year',
    'inviter_name',
    'primary_location',
    'secondary_location',
    'linkedin_url',
    'experience_summary',
    'expertise_summary',
    'industries_to_add_value',
    'industries_to_extend_expertise',
    'availability',
    'gender',
    'age',
    'privacy_acknowledged_at',
    'privacy_acknowledgement_version',
    'submitted_at',
    'reviewed_at',
    'reviewed_by',
    'review_notes',
])]
class MembershipApplication extends Model
{
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ApplicationRevision::class);
    }

    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
            'affiliation_type' => AffiliationType::class,
            'is_alumnus' => 'boolean',
            'industries_to_add_value' => 'array',
            'industries_to_extend_expertise' => 'array',
            'privacy_acknowledged_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
