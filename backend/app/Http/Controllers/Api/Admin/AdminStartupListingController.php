<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StartupListing;
use App\Notifications\StartupListingApproved;
use App\Notifications\StartupListingNeedsMoreInfo;
use App\Notifications\StartupListingRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminStartupListingController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ApprovalStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = StartupListing::query()
            ->with('owner:id,name,email')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        if (! empty($validated['status'])) {
            $query->where('approval_status', $validated['status']);
        }

        return $query->paginate($validated['per_page'] ?? 25);
    }

    public function show(StartupListing $listing): JsonResponse
    {
        $listing->load([
            'owner:id,name,email',
            'reviewer:id,name,email',
            'revisions' => fn ($q) => $q->latest('id'),
            'revisions.actor:id,name,email',
        ]);

        return response()->json(['data' => $listing]);
    }

    public function approve(Request $request, StartupListing $listing): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string'],
        ]);

        return $this->transition($request, $listing, [
            'approval_status' => ApprovalStatus::Approved,
            'content_status' => ContentStatus::Published,
            'review_notes' => $validated['review_notes'] ?? null,
            'action' => 'startup_listings.approve',
            'listing_timestamp' => 'approved_at',
            'notification' => StartupListingApproved::class,
        ]);
    }

    public function reject(Request $request, StartupListing $listing): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        return $this->transition($request, $listing, [
            'approval_status' => ApprovalStatus::Rejected,
            'content_status' => ContentStatus::Hidden,
            'review_notes' => $validated['review_notes'],
            'action' => 'startup_listings.reject',
            'listing_timestamp' => 'rejected_at',
            // Rejection emails are opt-in.
            'notification' => ($validated['send_email'] ?? false) ? StartupListingRejected::class : null,
        ]);
    }

    public function requestInfo(Request $request, StartupListing $listing): JsonResponse
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string'],
        ]);

        return $this->transition($request, $listing, [
            'approval_status' => ApprovalStatus::NeedsMoreInfo,
            'content_status' => ContentStatus::Draft,
            'review_notes' => $validated['review_notes'],
            'action' => 'startup_listings.request_info',
            'listing_timestamp' => null,
            'notification' => StartupListingNeedsMoreInfo::class,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function transition(Request $request, StartupListing $listing, array $config): JsonResponse
    {
        $response = DB::transaction(function () use ($request, $listing, $config): JsonResponse {
            $admin = $request->user();

            $before = [
                'approval_status' => $listing->approval_status?->value,
                'content_status' => $listing->content_status?->value,
                'review_notes' => $listing->review_notes,
                'reviewed_by' => $listing->reviewed_by,
            ];

            $listing->approval_status = $config['approval_status'];
            $listing->content_status = $config['content_status'];
            $listing->review_notes = $config['review_notes'];
            $listing->reviewed_at = now();
            $listing->reviewed_by = $admin->id;

            if ($config['listing_timestamp'] !== null) {
                $listing->{$config['listing_timestamp']} = now();
            }

            $listing->save();

            $after = [
                'approval_status' => $listing->approval_status?->value,
                'content_status' => $listing->content_status?->value,
                'review_notes' => $listing->review_notes,
                'reviewed_by' => $listing->reviewed_by,
            ];

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => $config['action'],
                'auditable_type' => StartupListing::class,
                'auditable_id' => $listing->id,
                'old_values' => ['listing' => $before],
                'new_values' => ['listing' => $after],
                'metadata' => ['owner_id' => $listing->owner_id],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $listing->load([
                'owner:id,name,email',
                'reviewer:id,name,email',
            ]);

            return response()->json(['data' => $listing]);
        });

        // Notifications fire after the DB transaction commits.
        $notificationClass = $config['notification'] ?? null;
        if ($notificationClass !== null) {
            $owner = $listing->owner()->first();
            if ($owner) {
                $owner->notify(new $notificationClass($listing->fresh()));
            }
        }

        return $response;
    }
}
