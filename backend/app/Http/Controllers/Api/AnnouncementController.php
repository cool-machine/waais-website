<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ]);

        $query = $this->memberQuery($request);

        $paginator = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 12);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Announcement $announcement) => $this->project($announcement))
        );

        return response()->json($paginator);
    }

    public function show(Request $request, int $announcement): JsonResponse
    {
        $model = $this->memberQuery($request)->findOrFail($announcement);

        return response()->json(['data' => $this->project($model)]);
    }

    private function memberQuery(Request $request): Builder
    {
        $user = $request->user();
        $audiences = ['all_members'];
        if ($user->isAdmin()) {
            $audiences[] = 'admins';
        }

        return Announcement::query()
            ->where('content_status', ContentStatus::Published)
            ->whereIn('visibility', [
                ContentVisibility::MembersOnly,
                ContentVisibility::Mixed,
                ContentVisibility::Public,
            ])
            ->whereIn('audience', $audiences);
    }

    /**
     * @return array<string, mixed>
     */
    private function project(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'summary' => $announcement->summary,
            'body' => $announcement->body,
            'visibility' => $announcement->visibility?->value,
            'audience' => $announcement->audience,
            'channel' => $announcement->channel,
            'action_label' => $announcement->action_label,
            'action_url' => $announcement->action_url,
            'published_at' => $announcement->published_at?->toIso8601String(),
        ];
    }
}
