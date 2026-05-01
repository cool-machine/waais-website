<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminEventController extends Controller
{
    /**
     * List events for the admin queue. Filterable by content_status,
     * visibility, and a coarse `time` window (upcoming / past / all).
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'content_status' => ['nullable', Rule::enum(ContentStatus::class)],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'time' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = Event::query()
            ->with('creator:id,name,email')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        if (! empty($validated['content_status'])) {
            $query->where('content_status', $validated['content_status']);
        }
        if (! empty($validated['visibility'])) {
            $query->where('visibility', $validated['visibility']);
        }

        $time = $validated['time'] ?? 'all';
        if ($time === 'upcoming') {
            $query->where('starts_at', '>=', now());
        } elseif ($time === 'past') {
            $query->where('starts_at', '<', now());
        }

        return $query->paginate($validated['per_page'] ?? 25);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load('creator:id,name,email');

        return response()->json(['data' => $event]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $event = DB::transaction(function () use ($validated, $request) {
            $event = new Event($validated);
            $event->created_by = $request->user()->id;
            // New events start as drafts. Admins must explicitly publish.
            $event->content_status = ContentStatus::Draft;
            $event->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'events.create',
                'auditable_type' => Event::class,
                'auditable_id' => $event->id,
                'old_values' => null,
                'new_values' => ['event' => $this->snapshot($event)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $event;
        });

        return response()->json(['data' => $event->fresh()->load('creator:id,name,email')], 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate($this->rules(updating: true));

        DB::transaction(function () use ($validated, $event, $request): void {
            $before = $this->snapshot($event);

            $event->fill($validated);
            $event->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'events.update',
                'auditable_type' => Event::class,
                'auditable_id' => $event->id,
                'old_values' => ['event' => $before],
                'new_values' => ['event' => $this->snapshot($event)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $event->fresh()->load('creator:id,name,email')]);
    }

    public function publish(Request $request, Event $event): JsonResponse
    {
        return $this->transitionContentStatus($request, $event, [
            'content_status' => ContentStatus::Published,
            'timestamp_field' => 'published_at',
            'action' => 'events.publish',
        ]);
    }

    public function hide(Request $request, Event $event): JsonResponse
    {
        return $this->transitionContentStatus($request, $event, [
            'content_status' => ContentStatus::Hidden,
            'timestamp_field' => 'hidden_at',
            'action' => 'events.hide',
        ]);
    }

    public function archive(Request $request, Event $event): JsonResponse
    {
        return $this->transitionContentStatus($request, $event, [
            'content_status' => ContentStatus::Archived,
            'timestamp_field' => 'archived_at',
            'action' => 'events.archive',
        ]);
    }

    /**
     * Cancel an event. Cancellation is independent of content_status:
     * a cancelled event remains visible to admins (so they can see and
     * restore it) but is filtered out of every public surface.
     */
    public function cancel(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'cancellation_note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($event, $validated, $request): void {
            abort_if($event->cancelled_at !== null, 409, 'Event is already cancelled.');

            $before = $this->snapshot($event);

            $event->cancelled_at = now();
            $event->cancellation_note = $validated['cancellation_note'] ?? null;
            $event->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'events.cancel',
                'auditable_type' => Event::class,
                'auditable_id' => $event->id,
                'old_values' => ['event' => $before],
                'new_values' => ['event' => $this->snapshot($event)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $event->fresh()->load('creator:id,name,email')]);
    }

    /**
     * @param  array{ content_status: ContentStatus, timestamp_field: string, action: string }  $config
     */
    private function transitionContentStatus(Request $request, Event $event, array $config): JsonResponse
    {
        DB::transaction(function () use ($event, $config, $request): void {
            $before = $this->snapshot($event);

            $event->content_status = $config['content_status'];
            $event->{$config['timestamp_field']} = now();
            $event->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => $config['action'],
                'auditable_type' => Event::class,
                'auditable_id' => $event->id,
                'old_values' => ['event' => $before],
                'new_values' => ['event' => $this->snapshot($event)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $event->fresh()->load('creator:id,name,email')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Event $event): array
    {
        return [
            'title' => $event->title,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'content_status' => $event->content_status?->value,
            'visibility' => $event->visibility?->value,
            'cancelled_at' => $event->cancelled_at?->toIso8601String(),
            'recap_content' => $event->recap_content,
            'capacity_limit' => $event->capacity_limit,
            'waitlist_open' => $event->waitlist_open,
            'reminder_days_before' => $event->reminder_days_before,
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
            'summary' => [$required, 'string'],
            'description' => [$required, 'string'],
            'starts_at' => [$required, 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'registration_url' => ['nullable', 'url', 'max:500'],
            'capacity_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'waitlist_open' => ['nullable', 'boolean'],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'recap_content' => ['nullable', 'string'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }
}
