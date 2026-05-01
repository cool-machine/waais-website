<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'membership_application_id',
    'actor_id',
    'changed_fields',
    'old_values',
    'new_values',
    'change_note',
])]
class ApplicationRevision extends Model
{
    public function membershipApplication(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
