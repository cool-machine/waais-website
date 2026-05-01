<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request): array {
        $user = $request->user();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'approval_status' => $user->approval_status?->value,
            'affiliation_type' => $user->affiliation_type?->value,
            'permission_role' => $user->permission_role?->value,
            'can_access_member_areas' => $user->canAccessMemberAreas(),
            'can_publish_public_content' => $user->canPublishPublicContent(),
            'can_manage_admin_privileges' => $user->canManageAdminPrivileges(),
        ];
    });

    Route::get('/member/ping', fn (): array => ['ok' => true])->middleware('member.access');
});
