<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Services\AnnouncementEmailFanout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'content_status' => ['nullable', Rule::enum(ContentStatus::class)],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'audience' => ['nullable', Rule::in(['all_members', 'admins'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = Announcement::query()
            ->with('creator:id,name,email')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($validated['content_status'])) {
            $query->where('content_status', $validated['content_status']);
        }
        if (! empty($validated['visibility'])) {
            $query->where('visibility', $validated['visibility']);
        }
        if (! empty($validated['audience'])) {
            $query->where('audience', $validated['audience']);
        }

        return $query->paginate($validated['per_page'] ?? 25);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load('creator:id,name,email');

        return response()->json(['data' => $announcement]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $announcement = DB::transaction(function () use ($validated, $request) {
            $announcement = new Announcement($validated);
            $announcement->created_by = $request->user()->id;
            $announcement->content_status = ContentStatus::Draft;
            $announcement->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'announcements.create',
                'auditable_type' => Announcement::class,
                'auditable_id' => $announcement->id,
                'old_values' => null,
                'new_values' => ['announcement' => $this->snapshot($announcement)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $announcement;
        });

        return response()->json(['data' => $announcement->fresh()->load('creator:id,name,email')], 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validate($this->rules(updating: true));

        DB::transaction(function () use ($validated, $announcement, $request): void {
            $before = $this->snapshot($announcement);

            $announcement->fill($validated);
            $announcement->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'announcements.update',
                'auditable_type' => Announcement::class,
                'auditable_id' => $announcement->id,
                'old_values' => ['announcement' => $before],
                'new_values' => ['announcement' => $this->snapshot($announcement)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $announcement->fresh()->load('creator:id,name,email')]);
    }

    public function publish(Request $request, Announcement $announcement, AnnouncementEmailFanout $fanout): JsonResponse
    {
        $response = $this->transitionContentStatus($request, $announcement, [
            'content_status' => ContentStatus::Published,
            'timestamp_field' => 'published_at',
            'action' => 'announcements.publish',
        ]);

        $fanout->send($announcement->fresh());

        return $response;
    }

    public function hide(Request $request, Announcement $announcement): JsonResponse
    {
        return $this->transitionContentStatus($request, $announcement, [
            'content_status' => ContentStatus::Hidden,
            'timestamp_field' => 'hidden_at',
            'action' => 'announcements.hide',
        ]);
    }

    public function archive(Request $request, Announcement $announcement): JsonResponse
    {
        return $this->transitionContentStatus($request, $announcement, [
            'content_status' => ContentStatus::Archived,
            'timestamp_field' => 'archived_at',
            'action' => 'announcements.archive',
        ]);
    }

    /**
     * @param  array{ content_status: ContentStatus, timestamp_field: string, action: string }  $config
     */
    private function transitionContentStatus(Request $request, Announcement $announcement, array $config): JsonResponse
    {
        DB::transaction(function () use ($announcement, $config, $request): void {
            $before = $this->snapshot($announcement);

            $announcement->content_status = $config['content_status'];
            $announcement->{$config['timestamp_field']} = now();
            $announcement->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => $config['action'],
                'auditable_type' => Announcement::class,
                'auditable_id' => $announcement->id,
                'old_values' => ['announcement' => $before],
                'new_values' => ['announcement' => $this->snapshot($announcement)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $announcement->fresh()->load('creator:id,name,email')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Announcement $announcement): array
    {
        return [
            'title' => $announcement->title,
            'content_status' => $announcement->content_status?->value,
            'visibility' => $announcement->visibility?->value,
            'audience' => $announcement->audience,
            'channel' => $announcement->channel,
            'published_at' => $announcement->published_at?->toIso8601String(),
            'hidden_at' => $announcement->hidden_at?->toIso8601String(),
            'archived_at' => $announcement->archived_at?->toIso8601String(),
            'action_url' => $announcement->action_url,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'body' => [$required, 'string'],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'audience' => ['nullable', Rule::in(['all_members', 'admins'])],
            'channel' => ['nullable', Rule::in(['dashboard', 'email_dashboard'])],
            'action_label' => ['nullable', 'string', 'max:255'],
            'action_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
