<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\HomepageCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHomepageCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ]);

        $query = $this->publicQuery();
        if (! empty($validated['section'])) {
            $query->where('section', $validated['section']);
        }

        $paginator = $query
            ->orderBy('section')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 48);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (HomepageCard $card) => $this->project($card))
        );

        return response()->json($paginator);
    }

    public function show(int $homepageCard): JsonResponse
    {
        $model = $this->publicQuery()->findOrFail($homepageCard);

        return response()->json(['data' => $this->project($model)]);
    }

    private function publicQuery(): Builder
    {
        return HomepageCard::query()
            ->where('content_status', ContentStatus::Published)
            ->whereIn('visibility', [ContentVisibility::Public, ContentVisibility::Mixed]);
    }

    /**
     * @return array<string, mixed>
     */
    private function project(HomepageCard $card): array
    {
        return [
            'id' => $card->id,
            'section' => $card->section,
            'eyebrow' => $card->eyebrow,
            'title' => $card->title,
            'body' => $card->body,
            'link_label' => $card->link_label,
            'link_url' => $card->link_url,
            'visibility' => $card->visibility?->value,
            'published_at' => $card->published_at?->toIso8601String(),
        ];
    }
}
