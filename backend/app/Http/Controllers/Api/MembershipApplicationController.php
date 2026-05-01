<?php

namespace App\Http\Controllers\Api;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\ApplicationRevision;
use App\Models\MembershipApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipApplicationController extends Controller
{
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

        $this->fillAndSubmit($application, $request, 'submitted');

        return response()->json(['data' => $application->fresh()], $application->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request): JsonResponse
    {
        $application = $request->user()->membershipApplications()->latest()->firstOrFail();

        abort_if($application->approval_status === ApprovalStatus::Approved, 409, 'Approved applications cannot be edited by applicants.');

        $this->fillAndSubmit($application, $request, 'updated');

        return response()->json(['data' => $application->fresh()]);
    }

    public function reapply(Request $request): JsonResponse
    {
        $application = $request->user()->membershipApplications()->latest()->firstOrFail();

        abort_unless($application->approval_status === ApprovalStatus::Rejected, 409, 'Only rejected applications can be reapplied.');

        $this->fillAndSubmit($application, $request, 'reapplied');

        return response()->json(['data' => $application->fresh()]);
    }

    private function fillAndSubmit(MembershipApplication $application, Request $request, string $note): void
    {
        $validated = $request->validate($this->rules());
        $before = $application->exists ? $application->getAttributes() : [];

        $application->fill($validated);
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
    private function rules(): array
    {
        return [
            'affiliation_type' => ['nullable', Rule::enum(AffiliationType::class)],
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone_whatsapp' => ['nullable', 'string', 'max:255'],
            'is_alumnus' => ['required', 'boolean'],
            'school_affiliation' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'between:1800,2100'],
            'inviter_name' => ['nullable', 'string', 'max:255'],
            'primary_location' => ['nullable', 'string', 'max:255'],
            'secondary_location' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'experience_summary' => ['nullable', 'string'],
            'expertise_summary' => ['nullable', 'string'],
            'industries_to_add_value' => ['nullable', 'array'],
            'industries_to_add_value.*' => ['string', 'max:120'],
            'industries_to_extend_expertise' => ['nullable', 'array'],
            'industries_to_extend_expertise.*' => ['string', 'max:120'],
            'availability' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'between:13,120'],
        ];
    }

    private function syncUserStatus(Request $request, MembershipApplication $application): void
    {
        $request->user()->forceFill([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
            'affiliation_type' => $application->affiliation_type,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function recordRevision(MembershipApplication $application, Request $request, array $before, string $note): void
    {
        $fields = array_keys($this->rules());
        $fields = array_values(array_filter($fields, fn (string $field): bool => ! str_contains($field, '*')));

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
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (in_array($field, ['industries_to_add_value', 'industries_to_extend_expertise'], true) && is_string($value)) {
            return json_decode($value, true);
        }

        return $value;
    }
}
