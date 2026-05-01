<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MembershipApplication;
use App\Notifications\MembershipApplicationApproved;
use App\Notifications\MembershipApplicationNeedsMoreInfo;
use App\Notifications\MembershipApplicationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminMembershipApplicationController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ApprovalStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = MembershipApplication::query()
            ->with('applicant:id,name,email')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        if (! empty($validated['status'])) {
            $query->where('approval_status', $validated['status']);
        }

        return $query->paginate($validated['per_page'] ?? 25);
    }

    public function show(MembershipApplication $application): JsonResponse
    {
        $application->load([
            'applicant:id,name,email',
            'reviewer:id,name,email',
            'revisions' => fn ($q) => $q->latest('id'),
            'revisions.actor:id,name,email',
        ]);

        return response()->json(['data' => $application]);
    }

    public function approve(Request $request, MembershipApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string'],
        ]);

        return $this->transition($request, $application, [
            'application_status' => ApprovalStatus::Approved,
            'review_notes' => $validated['review_notes'] ?? null,
            'action' => 'applications.approve',
            'user_status' => ApprovalStatus::Approved,
            'promote_pending_to_member' => true,
            'sync_affiliation_from_application' => true,
            'user_timestamp' => 'approved_at',
            'notification' => MembershipApplicationApproved::class,
        ]);
    }

    public function reject(Request $request, MembershipApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        return $this->transition($request, $application, [
            'application_status' => ApprovalStatus::Rejected,
            'review_notes' => $validated['review_notes'],
            'action' => 'applications.reject',
            'user_status' => ApprovalStatus::Rejected,
            'promote_pending_to_member' => false,
            'sync_affiliation_from_application' => false,
            'user_timestamp' => 'rejected_at',
            // Rejection emails are opt-in: admin must explicitly request the email.
            'notification' => ($validated['send_email'] ?? false) ? MembershipApplicationRejected::class : null,
        ]);
    }

    public function requestInfo(Request $request, MembershipApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string'],
        ]);

        return $this->transition($request, $application, [
            'application_status' => ApprovalStatus::NeedsMoreInfo,
            'review_notes' => $validated['review_notes'],
            'action' => 'applications.request_info',
            'user_status' => ApprovalStatus::NeedsMoreInfo,
            'promote_pending_to_member' => false,
            'sync_affiliation_from_application' => false,
            'user_timestamp' => null,
            'notification' => MembershipApplicationNeedsMoreInfo::class,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function transition(Request $request, MembershipApplication $application, array $config): JsonResponse
    {
        $response = DB::transaction(function () use ($request, $application, $config): JsonResponse {
            $admin = $request->user();
            $applicant = $application->applicant()->lockForUpdate()->first();

            $appBefore = [
                'approval_status' => $application->approval_status?->value,
                'review_notes' => $application->review_notes,
                'reviewed_by' => $application->reviewed_by,
            ];

            $userBefore = [
                'approval_status' => $applicant?->approval_status?->value,
                'permission_role' => $applicant?->permission_role?->value,
                'affiliation_type' => $applicant?->affiliation_type?->value,
            ];

            $application->approval_status = $config['application_status'];
            $application->review_notes = $config['review_notes'];
            $application->reviewed_at = now();
            $application->reviewed_by = $admin->id;
            $application->save();

            if ($applicant) {
                $applicant->approval_status = $config['user_status'];

                if ($config['promote_pending_to_member'] && $applicant->permission_role === PermissionRole::PendingUser) {
                    $applicant->permission_role = PermissionRole::Member;
                }

                if ($config['sync_affiliation_from_application'] && $application->affiliation_type !== null) {
                    $applicant->affiliation_type = $application->affiliation_type;
                }

                if ($config['user_timestamp'] !== null) {
                    $applicant->{$config['user_timestamp']} = now();
                }

                $applicant->save();
            }

            $appAfter = [
                'approval_status' => $application->approval_status?->value,
                'review_notes' => $application->review_notes,
                'reviewed_by' => $application->reviewed_by,
            ];

            $userAfter = [
                'approval_status' => $applicant?->approval_status?->value,
                'permission_role' => $applicant?->permission_role?->value,
                'affiliation_type' => $applicant?->affiliation_type?->value,
            ];

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => $config['action'],
                'auditable_type' => MembershipApplication::class,
                'auditable_id' => $application->id,
                'old_values' => ['application' => $appBefore, 'user' => $userBefore],
                'new_values' => ['application' => $appAfter, 'user' => $userAfter],
                'metadata' => ['applicant_id' => $applicant?->id],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $application->load([
                'applicant:id,name,email',
                'reviewer:id,name,email',
            ]);

            return response()->json(['data' => $application]);
        });

        // Notifications fire after the DB transaction commits so a failed save
        // never produces a stray email.
        $notificationClass = $config['notification'] ?? null;
        if ($notificationClass !== null) {
            $applicant = $application->applicant()->first();
            if ($applicant) {
                $applicant->notify(new $notificationClass($application->fresh()));
            }
        }

        return $response;
    }
}
