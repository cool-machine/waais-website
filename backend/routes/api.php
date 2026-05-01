<?php

use App\Http\Controllers\Api\Admin\AdminMembershipApplicationController;
use App\Http\Controllers\Api\MembershipApplicationController;
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

    Route::get('/membership-application', [MembershipApplicationController::class, 'show']);
    Route::post('/membership-application', [MembershipApplicationController::class, 'submit']);
    Route::patch('/membership-application', [MembershipApplicationController::class, 'update']);
    Route::post('/membership-application/reapply', [MembershipApplicationController::class, 'reapply']);

    Route::middleware('admin.access')->prefix('admin')->group(function (): void {
        Route::get('/applications', [AdminMembershipApplicationController::class, 'index']);
        Route::get('/applications/{application}', [AdminMembershipApplicationController::class, 'show']);
        Route::post('/applications/{application}/approve', [AdminMembershipApplicationController::class, 'approve']);
        Route::post('/applications/{application}/reject', [AdminMembershipApplicationController::class, 'reject']);
        Route::post('/applications/{application}/request-info', [AdminMembershipApplicationController::class, 'requestInfo']);
    });
});
