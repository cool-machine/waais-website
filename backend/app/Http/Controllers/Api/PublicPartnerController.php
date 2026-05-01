<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ]);

        $paginator = $this->publicQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 12);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Partner $partner) => $this->project($partner))
        );

        return response()->json($paginator);
    }

    public function show(int $partner): JsonResponse
    {
        $model = $this->publicQuery()->findOrFail($partner);

        return response()->json(['data' => $this->project($model)]);
    }

    /**
     * Anything not (Published) and (Public OR Mixed) is treated as if
     * it does not exist. Members-only partner records can surface
     * later through authenticated member pages.
     */
    private function publicQuery(): Builder
    {
        return Partner::query()
            ->where('content_status', ContentStatus::Published)
            ->whereIn('visibility', [ContentVisibility::Public, ContentVisibility::Mixed]);
    }

    /**
     * Explicit allowlist projection. Internal fields — created_by,
     * creator, content_status, hidden_at, archived_at, created_at,
     * updated_at — must never appear here.
     *
     * @return array<string, mixed>
     */
    private function project(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'partner_type' => $partner->partner_type,
            'summary' => $partner->summary,
            'description' => $partner->description,
            'website_url' => $partner->website_url,
            'logo_url' => $partner->logo_url,
            'visibility' => $partner->visibility?->value,
            'published_at' => $partner->published_at?->toIso8601String(),
        ];
    }
}
