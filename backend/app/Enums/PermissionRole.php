<?php

namespace App\Enums;

enum PermissionRole: string
{
    case Public = 'public';
    case PendingUser = 'pending_user';
    case Member = 'member';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function includesMemberAccess(): bool
    {
        return in_array($this, [self::Member, self::Admin, self::SuperAdmin], true);
    }

    public function includesAdminAccess(): bool
    {
        return in_array($this, [self::Admin, self::SuperAdmin], true);
    }
}
