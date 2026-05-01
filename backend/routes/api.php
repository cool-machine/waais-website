<?php

use App\Http\Controllers\Api\Admin\AdminMembershipApplicationController;
use App\Http\Controllers\Api\Admin\AdminStartupListingController;
use App\Http\Controllers\Api\Admin\AdminUserRoleController;
use App\Http\Controllers\Api\MembershipApplicationController;
use App\Http\Controllers\Api\PublicStartupListingController;
use App\Http\Controllers\Api\StartupListingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public, anonymous endpoints. Filtered to content_status=published AND
// visibility=public; anything else is invisible (404 on show).
Route::prefix('public')->group(function (): void {
    Route::get('/startup-listings', [PublicStartupListingController::class, 'index']);
    Route::get('/startup-listings/{listing}', [PublicStartupListingController::class, 'show']);
});

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

    Route::middleware('member.access')->group(function (): void {
        Route::get('/startup-listings', [StartupListingController::class, 'index']);
        Route::post('/startup-listings', [StartupListingController::class, 'store']);
        Route::get('/startup-listings/{listing}', [StartupListingController::class, 'show']);
        Route::patch('/startup-listings/{listing}', [StartupListingController::class, 'update']);
    });

    Route::middleware('admin.access')->prefix('admin')->group(function (): void {
        Route::get('/applications', [AdminMembershipApplicationController::class, 'index']);
        Route::get('/applications/{application}', [AdminMembershipApplicationController::class, 'show']);
        Route::post('/applications/{application}/approve', [AdminMembershipApplicationController::class, 'approve']);
        Route::post('/applications/{application}/reject', [AdminMembershipApplicationController::class, 'reject']);
        Route::post('/applications/{application}/request-info', [AdminMembershipApplicationController::class, 'requestInfo']);

        Route::get('/startup-listings', [AdminStartupListingController::class, 'index']);
        Route::get('/startup-listings/{listing}', [AdminStartupListingController::class, 'show']);
        Route::post('/startup-listings/{listing}/approve', [AdminStartupListingController::class, 'approve']);
        Route::post('/startup-listings/{listing}/reject', [AdminStartupListingController::class, 'reject']);
        Route::post('/startup-listings/{listing}/request-info', [AdminStartupListingController::class, 'requestInfo']);

        Route::middleware('super_admin.access')->group(function (): void {
            Route::post('/users/{user}/promote-admin', [AdminUserRoleController::class, 'promoteAdmin']);
            Route::post('/users/{user}/demote-admin', [AdminUserRoleController::class, 'demoteAdmin']);
            Route::post('/users/{user}/promote-super-admin', [AdminUserRoleController::class, 'promoteSuperAdmin']);
            Route::post('/users/{user}/demote-super-admin', [AdminUserRoleController::class, 'demoteSuperAdmin']);
        });
    });
});
