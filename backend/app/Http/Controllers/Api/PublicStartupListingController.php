<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\StartupListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicStartupListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ]);

        $paginator = $this->publicQuery()
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 12);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (StartupListing $listing) => $this->project($listing))
        );

        return response()->json($paginator);
    }

    public function show(int $listing): JsonResponse
    {
        $model = $this->publicQuery()->findOrFail($listing);

        return response()->json(['data' => $this->project($model)]);
    }

    /**
     * Anything not in (Published, Public) is treated as if it does not exist.
     */
    private function publicQuery(): Builder
    {
        return StartupListing::query()
            ->where('content_status', ContentStatus::Published)
            ->where('visibility', ContentVisibility::Public);
    }

    /**
     * Explicit allowlist projection. Any internal field — review_notes,
     * owner_id, reviewed_by, reviewed_at, submitted_at, rejected_at,
     * submitter_role, approval_status — must never appear here.
     *
     * @return array<string, mixed>
     */
    private function project(StartupListing $listing): array
    {
        return [
            'id' => $listing->id,
            'name' => $listing->name,
            'tagline' => $listing->tagline,
            'description' => $listing->description,
            'website_url' => $listing->website_url,
            'logo_url' => $listing->logo_url,
            'industry' => $listing->industry,
            'stage' => $listing->stage,
            'location' => $listing->location,
            'founders' => $listing->founders,
            'linkedin_url' => $listing->linkedin_url,
            'approved_at' => $listing->approved_at?->toIso8601String(),
        ];
    }
}
