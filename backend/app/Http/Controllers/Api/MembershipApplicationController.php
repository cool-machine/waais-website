<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\ApplicationRevision;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\MembershipApplicationReceivedByAdmin;
use App\Notifications\MembershipApplicationSubmitted;
use App\Support\MembershipApplicationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class MembershipApplicationController extends Controller
{
    private const PRIVACY_ACKNOWLEDGEMENT_VERSION = MembershipApplicationRules::PRIVACY_ACKNOWLEDGEMENT_VERSION;

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->membershipApplications()->latest()->first(),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $application = $request->user()->membershipApplications()->latest()->first();

        abort_if($application?->approval_status === ApprovalStatus::Approved, 409, 'Approved applications cannot be resubmitted.');
        abort_if($application?->approval_status === ApprovalStatus::Rejected, 409, 'Rejected applications must use the reapply endpoint.');

        $application ??= new MembershipApplication(['applicant_id' => $request->user()->id]);

        $this->fillAndSubmit($application, $request, 'submitted', requirePrivacyAcknowledgement: true);
        $this->notifyOnSubmission($application, $request->user());

        return response()->json(['data' => $application->fresh()], $application->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request): JsonResponse
    {
        $application = $request->user()->membershipApplications()->latest()->firstOrFail();

        abort_if($application->approval_status === ApprovalStatus::Approved, 409, 'Approved applications cannot be edited by applicants.');

        // Edits do not fire notifications. Admins see the bumped submitted_at on the queue.
        $this->fillAndSubmit($application, $request, 'updated');

        return response()->json(['data' => $application->fresh()]);
    }

    public function reapply(Request $request): JsonResponse
    {
        $application = $request->user()->membershipApplications()->latest()->firstOrFail();

        abort_unless($application->approval_status === ApprovalStatus::Rejected, 409, 'Only rejected applications can be reapplied.');

        $this->fillAndSubmit($application, $request, 'reapplied', requirePrivacyAcknowledgement: true);
        $this->notifyOnSubmission($application, $request->user());

        return response()->json(['data' => $application->fresh()]);
    }

    private function fillAndSubmit(
        MembershipApplication $application,
        Request $request,
        string $note,
        bool $requirePrivacyAcknowledgement = false,
    ): void
    {
        $validated = $request->validate($this->rules($requirePrivacyAcknowledgement));
        $before = $application->exists ? $application->getAttributes() : [];
        $privacyAcknowledged = (bool) ($validated['privacy_acknowledgement'] ?? false);
        unset($validated['privacy_acknowledgement']);

        $application->fill($validated);
        if ($privacyAcknowledged) {
            $application->privacy_acknowledged_at = now();
            $application->privacy_acknowledgement_version = self::PRIVACY_ACKNOWLEDGEMENT_VERSION;
        }
        $application->approval_status = ApprovalStatus::Submitted;
        $application->submitted_at = now();
        $application->reviewed_at = null;
        $application->reviewed_by = null;
        $application->review_notes = null;
        $application->save();

        $this->syncUserStatus($request, $application);
        $this->recordRevision($application, $request, $before, $note);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $requirePrivacyAcknowledgement = false): array
    {
        return MembershipApplicationRules::rules($requirePrivacyAcknowledgement);
    }

    private function syncUserStatus(Request $request, MembershipApplication $application): void
    {
        $user = $request->user();

        // Admins and super admins keep their role and approved status when
        // they submit or update their own application; only their
        // affiliation is synced.
        if ($user->permission_role?->includesAdminAccess()) {
            $user->forceFill([
                'affiliation_type' => $application->affiliation_type,
            ])->save();

            return;
        }

        $user->forceFill([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
            'affiliation_type' => $application->affiliation_type,
        ])->save();
    }

    private function notifyOnSubmission(MembershipApplication $application, User $applicant): void
    {
        $this->afterResponse(function () use ($application, $applicant): void {
            $applicant->notify(new MembershipApplicationSubmitted($application));

            $admins = User::superAdmins()->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new MembershipApplicationReceivedByAdmin($application));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function recordRevision(MembershipApplication $application, Request $request, array $before, string $note): void
    {
        $fields = array_keys($this->rules());
        $fields = array_values(array_filter($fields, fn (string $field): bool => ! str_contains($field, '*')));
        $fields = array_values(array_diff($fields, ['privacy_acknowledgement']));
        $fields[] = 'privacy_acknowledged_at';
        $fields[] = 'privacy_acknowledgement_version';

        $changed = [];
        $old = [];
        $new = [];

        foreach ($fields as $field) {
            $oldValue = $this->revisionValue($field, $before[$field] ?? null);
            $newValue = $this->revisionValue($field, $application->getAttribute($field));

            if ($oldValue != $newValue) {
                $changed[] = $field;
                $old[$field] = $oldValue;
                $new[$field] = $newValue;
            }
        }

        if ($changed === []) {
            return;
        }

        ApplicationRevision::create([
            'membership_application_id' => $application->id,
            'actor_id' => $request->user()->id,
            'changed_fields' => $changed,
            'old_values' => $old,
            'new_values' => $new,
            'change_note' => $note,
        ]);
    }

    private function revisionValue(string $field, mixed $value): mixed
    {
        if ($field === 'privacy_acknowledged_at' && $value !== null) {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (in_array($field, ['industries_to_add_value', 'industries_to_extend_expertise'], true) && is_string($value)) {
            return json_decode($value, true);
        }

        return $value;
    }
}
