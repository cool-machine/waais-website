<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminUserRoleController extends Controller
{
    public function promoteAdmin(Request $request, User $user): JsonResponse
    {
        abort_unless(
            $user->approval_status === ApprovalStatus::Approved,
            Response::HTTP_CONFLICT,
            'Only approved members can be promoted to admin.',
        );

        return $this->transition($request, $user, [
            'from' => PermissionRole::Member,
            'to' => PermissionRole::Admin,
            'action' => 'role.promote_admin',
            'mismatch_message' => 'Only members can be promoted to admin.',
        ]);
    }

    public function demoteAdmin(Request $request, User $user): JsonResponse
    {
        return $this->transition($request, $user, [
            'from' => PermissionRole::Admin,
            'to' => PermissionRole::Member,
            'action' => 'role.demote_admin',
            'mismatch_message' => 'Only admins can be demoted to member.',
        ]);
    }

    public function promoteSuperAdmin(Request $request, User $user): JsonResponse
    {
        return $this->transition($request, $user, [
            'from' => PermissionRole::Admin,
            'to' => PermissionRole::SuperAdmin,
            'action' => 'role.promote_super_admin',
            'mismatch_message' => 'Only admins can be promoted to super admin.',
        ]);
    }

    public function demoteSuperAdmin(Request $request, User $user): JsonResponse
    {
        return $this->transition($request, $user, [
            'from' => PermissionRole::SuperAdmin,
            'to' => PermissionRole::Admin,
            'action' => 'role.demote_super_admin',
            'mismatch_message' => 'Only super admins can be demoted to admin.',
            'protect_last_super_admin' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function transition(Request $request, User $user, array $config): JsonResponse
    {
        return DB::transaction(function () use ($request, $user, $config): JsonResponse {
            $actor = $request->user();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            abort_unless(
                $user->permission_role === $config['from'],
                Response::HTTP_CONFLICT,
                $config['mismatch_message'],
            );

            if (! empty($config['protect_last_super_admin'])) {
                $remaining = User::query()
                    ->where('permission_role', PermissionRole::SuperAdmin)
                    ->where('id', '!=', $user->id)
                    ->count();

                abort_if(
                    $remaining === 0,
                    Response::HTTP_CONFLICT,
                    'Cannot demote the last super admin.',
                );
            }

            $before = ['permission_role' => $user->permission_role?->value];

            $user->permission_role = $config['to'];
            $user->save();

            $after = ['permission_role' => $user->permission_role?->value];

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => $config['action'],
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'old_values' => ['user' => $before],
                'new_values' => ['user' => $after],
                'metadata' => ['target_user_id' => $user->id],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['data' => $user->only([
                'id',
                'name',
                'email',
                'approval_status',
                'permission_role',
                'affiliation_type',
            ])]);
        });
    }
}
