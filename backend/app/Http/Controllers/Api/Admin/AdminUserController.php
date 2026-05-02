<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Listing fields admins are allowed to see. Internal/identity-leak
     * fields (password, remember_token, google_id, two_factor_*) are
     * intentionally excluded. Drift is locked down by
     * AdminUserApiTest::projection_excludes_internal_fields.
     *
     * @var list<string>
     */
    private const PROJECTION = [
        'id',
        'name',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'email_verified_at',
        'avatar_url',
        'approval_status',
        'affiliation_type',
        'permission_role',
        'approved_at',
        'rejected_at',
        'suspended_at',
        'created_at',
    ];

    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'permission_role' => ['nullable', Rule::enum(PermissionRole::class)],
            'approval_status' => ['nullable', Rule::enum(ApprovalStatus::class)],
            'affiliation_type' => ['nullable', Rule::enum(AffiliationType::class)],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = User::query()
            ->select(self::PROJECTION)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($validated['permission_role'])) {
            $query->where('permission_role', $validated['permission_role']);
        }
        if (! empty($validated['approval_status'])) {
            $query->where('approval_status', $validated['approval_status']);
        }
        if (! empty($validated['affiliation_type'])) {
            $query->where('affiliation_type', $validated['affiliation_type']);
        }

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('email', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('display_name', 'like', $term);
            });
        }

        return $query->paginate($validated['per_page'] ?? 25);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $user->only(self::PROJECTION)]);
    }
}
