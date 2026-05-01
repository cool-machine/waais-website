<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case None = 'none';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case NeedsMoreInfo = 'needs_more_info';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function grantsMemberAccess(): bool
    {
        return $this === self::Approved;
    }
}
