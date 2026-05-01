<?php

namespace App\Enums;

enum ContentVisibility: string
{
    case Public = 'public';
    case MembersOnly = 'members_only';
    case Mixed = 'mixed';
}
