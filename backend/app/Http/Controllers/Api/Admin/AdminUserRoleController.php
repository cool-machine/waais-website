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
    /**
     * Set a user's per-area admin permissions (events / partners / startups).
     * Granting any area makes them an admin; clearing all reverts them to
     * member. Super admins already hold every area and are left untouched.
     */
    public function setAdminAreas(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'boolean'],
            'partners' => ['required', 'boolean'],
            'startups' => ['required', 'boolean'],
        ]);

        abort_if(
            $user->permission_role === PermissionRole::SuperAdmin,
            Response::HTTP_CONFLICT,
            'Super admins already manage every area.',
        );

        abort_unless(
            $user->approval_status === ApprovalStatus::Approved,
            Response::HTTP_CONFLICT,
            'Only approved members can be given admin areas.',
        );

        return DB::transaction(function () use ($request, $user, $validated): JsonResponse {
            $actor = $request->user();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $anyArea = $validated['events'] || $validated['partners'] || $validated['startups'];

            $before = $this->areaSnapshot($user);

            $user->can_manage_events = $validated['events'];
            $user->can_manage_partners = $validated['partners'];
            $user->can_manage_startups = $validated['startups'];
            $user->permission_role = $anyArea ? PermissionRole::Admin : PermissionRole::Member;
            $user->save();

            AuditLog::create([
                'actor_id' => $actor->id,
                'action' => 'role.set_admin_areas',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'old_values' => ['user' => $before],
                'new_values' => ['user' => $this->areaSnapshot($user)],
                'metadata' => ['target_user_id' => $user->id],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['data' => $this->payload($user)]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function areaSnapshot(User $user): array
    {
        return [
            'permission_role' => $user->permission_role?->value,
            'can_manage_events' => (bool) $user->can_manage_events,
            'can_manage_partners' => (bool) $user->can_manage_partners,
            'can_manage_startups' => (bool) $user->can_manage_startups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'approval_status',
            'permission_role',
            'affiliation_type',
            'can_manage_events',
            'can_manage_partners',
            'can_manage_startups',
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

            return response()->json(['data' => $this->payload($user)]);
        });
    }
}
