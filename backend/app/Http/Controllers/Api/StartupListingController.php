<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\StartupListing;
use App\Models\StartupListingRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StartupListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->startupListings()
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function show(Request $request, StartupListing $listing): JsonResponse
    {
        $this->ensureOwner($request, $listing);

        $listing->load([
            'reviewer:id,name,email',
            'revisions' => fn ($q) => $q->latest('id'),
            'revisions.actor:id,name,email',
        ]);

        return response()->json(['data' => $listing]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $listing = new StartupListing($validated);
        $listing->owner_id = $request->user()->id;
        $listing->approval_status = ApprovalStatus::Submitted;
        $listing->content_status = ContentStatus::PendingReview;
        $listing->visibility ??= ContentVisibility::Public;
        $listing->submitted_at = now();
        $listing->save();

        $this->recordRevision($listing, $request, [], 'submitted');

        return response()->json(['data' => $listing->fresh()], 201);
    }

    public function update(Request $request, StartupListing $listing): JsonResponse
    {
        $this->ensureOwner($request, $listing);

        abort_if(
            $listing->approval_status === ApprovalStatus::Approved,
            Response::HTTP_CONFLICT,
            'Approved listings cannot be edited by the owner.',
        );

        $validated = $request->validate($this->rules());
        $before = $listing->getAttributes();

        $listing->fill($validated);

        // Re-submit on edit so admins re-review.
        $listing->approval_status = ApprovalStatus::Submitted;
        $listing->content_status = ContentStatus::PendingReview;
        $listing->submitted_at = now();
        $listing->reviewed_at = null;
        $listing->reviewed_by = null;
        $listing->review_notes = null;
        $listing->save();

        $this->recordRevision($listing, $request, $before, 'updated');

        return response()->json(['data' => $listing->fresh()]);
    }

    private function ensureOwner(Request $request, StartupListing $listing): void
    {
        abort_unless(
            $listing->owner_id === $request->user()->id,
            Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'industry' => ['required', 'string', 'max:120'],
            'stage' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'founders' => ['nullable', 'array'],
            'founders.*' => ['string', 'max:255'],
            'submitter_role' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function recordRevision(StartupListing $listing, Request $request, array $before, string $note): void
    {
        $fields = array_keys($this->rules());
        $fields = array_values(array_filter($fields, fn (string $field): bool => ! str_contains($field, '*')));

        $changed = [];
        $old = [];
        $new = [];

        foreach ($fields as $field) {
            $oldValue = $this->revisionValue($field, $before[$field] ?? null);
            $newValue = $this->revisionValue($field, $listing->getAttribute($field));

            if ($oldValue != $newValue) {
                $changed[] = $field;
                $old[$field] = $oldValue;
                $new[$field] = $newValue;
            }
        }

        if ($changed === []) {
            return;
        }

        StartupListingRevision::create([
            'startup_listing_id' => $listing->id,
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

        if ($field === 'founders' && is_string($value)) {
            return json_decode($value, true);
        }

        return $value;
    }
}
