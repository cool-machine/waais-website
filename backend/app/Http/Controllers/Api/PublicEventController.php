<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,48'],
            'time' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
        ]);

        $time = $validated['time'] ?? 'upcoming';

        $query = $this->publicQuery();

        // Sorting differs by window: upcoming events list the next one
        // first; past events list the most recent first.
        if ($time === 'upcoming') {
            $query->where('starts_at', '>=', now())->orderBy('starts_at')->orderBy('id');
        } elseif ($time === 'past') {
            $query->where('starts_at', '<', now())->orderByDesc('starts_at')->orderByDesc('id');
        } else {
            $query->orderByDesc('starts_at')->orderByDesc('id');
        }

        $paginator = $query->paginate($validated['per_page'] ?? 12);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Event $event) => $this->project($event))
        );

        return response()->json($paginator);
    }

    public function show(int $event): JsonResponse
    {
        $model = $this->publicQuery()->findOrFail($event);

        return response()->json(['data' => $this->project($model)]);
    }

    /**
     * Anything not (Published) and (Public OR Mixed) and not cancelled
     * is treated as if it does not exist. Members-only events are
     * intentionally invisible to anonymous callers (they will surface
     * later via the authenticated member events store).
     */
    private function publicQuery(): Builder
    {
        return Event::query()
            ->where('content_status', ContentStatus::Published)
            ->whereIn('visibility', [ContentVisibility::Public, ContentVisibility::Mixed])
            ->whereNull('cancelled_at');
    }

    /**
     * Explicit allowlist projection. Internal fields — created_by,
     * hidden_at, archived_at, cancelled_at, cancellation_note,
     * reminder_days_before, content_status — must never appear here.
     * The denylist test in PublicEventApiTest enforces drift.
     *
     * @return array<string, mixed>
     */
    private function project(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'summary' => $event->summary,
            'description' => $event->description,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'location' => $event->location,
            'format' => $event->format,
            'image_url' => $event->image_url,
            'registration_url' => $event->registration_url,
            'capacity_limit' => $event->capacity_limit,
            'waitlist_open' => $event->waitlist_open,
            'visibility' => $event->visibility?->value,
            'recap_content' => $event->recap_content,
            'status' => $event->derivedStatus(),
            'published_at' => $event->published_at?->toIso8601String(),
        ];
    }
}
