<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HomepageCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminHomepageCardController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'section' => ['nullable', 'string', 'max:255'],
            'content_status' => ['nullable', Rule::enum(ContentStatus::class)],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = HomepageCard::query()
            ->with('creator:id,name,email')
            ->orderBy('section')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! empty($validated['section'])) {
            $query->where('section', $validated['section']);
        }
        if (! empty($validated['content_status'])) {
            $query->where('content_status', $validated['content_status']);
        }
        if (! empty($validated['visibility'])) {
            $query->where('visibility', $validated['visibility']);
        }

        return $query->paginate($validated['per_page'] ?? 25);
    }

    public function show(HomepageCard $homepageCard): JsonResponse
    {
        $homepageCard->load('creator:id,name,email');

        return response()->json(['data' => $homepageCard]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $card = DB::transaction(function () use ($validated, $request) {
            $card = new HomepageCard($validated);
            $card->created_by = $request->user()->id;
            $card->content_status = ContentStatus::Draft;
            $card->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'homepage_cards.create',
                'auditable_type' => HomepageCard::class,
                'auditable_id' => $card->id,
                'old_values' => null,
                'new_values' => ['homepage_card' => $this->snapshot($card)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $card;
        });

        return response()->json(['data' => $card->fresh()->load('creator:id,name,email')], 201);
    }

    public function update(Request $request, HomepageCard $homepageCard): JsonResponse
    {
        $validated = $request->validate($this->rules(updating: true));

        DB::transaction(function () use ($validated, $homepageCard, $request): void {
            $before = $this->snapshot($homepageCard);

            $homepageCard->fill($validated);
            $homepageCard->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'homepage_cards.update',
                'auditable_type' => HomepageCard::class,
                'auditable_id' => $homepageCard->id,
                'old_values' => ['homepage_card' => $before],
                'new_values' => ['homepage_card' => $this->snapshot($homepageCard)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $homepageCard->fresh()->load('creator:id,name,email')]);
    }

    public function publish(Request $request, HomepageCard $homepageCard): JsonResponse
    {
        return $this->transitionContentStatus($request, $homepageCard, [
            'content_status' => ContentStatus::Published,
            'timestamp_field' => 'published_at',
            'action' => 'homepage_cards.publish',
        ]);
    }

    public function hide(Request $request, HomepageCard $homepageCard): JsonResponse
    {
        return $this->transitionContentStatus($request, $homepageCard, [
            'content_status' => ContentStatus::Hidden,
            'timestamp_field' => 'hidden_at',
            'action' => 'homepage_cards.hide',
        ]);
    }

    public function archive(Request $request, HomepageCard $homepageCard): JsonResponse
    {
        return $this->transitionContentStatus($request, $homepageCard, [
            'content_status' => ContentStatus::Archived,
            'timestamp_field' => 'archived_at',
            'action' => 'homepage_cards.archive',
        ]);
    }

    /**
     * @param  array{ content_status: ContentStatus, timestamp_field: string, action: string }  $config
     */
    private function transitionContentStatus(Request $request, HomepageCard $homepageCard, array $config): JsonResponse
    {
        DB::transaction(function () use ($homepageCard, $config, $request): void {
            $before = $this->snapshot($homepageCard);

            $homepageCard->content_status = $config['content_status'];
            $homepageCard->{$config['timestamp_field']} = now();
            $homepageCard->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => $config['action'],
                'auditable_type' => HomepageCard::class,
                'auditable_id' => $homepageCard->id,
                'old_values' => ['homepage_card' => $before],
                'new_values' => ['homepage_card' => $this->snapshot($homepageCard)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $homepageCard->fresh()->load('creator:id,name,email')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(HomepageCard $card): array
    {
        return [
            'section' => $card->section,
            'eyebrow' => $card->eyebrow,
            'title' => $card->title,
            'content_status' => $card->content_status?->value,
            'visibility' => $card->visibility?->value,
            'published_at' => $card->published_at?->toIso8601String(),
            'hidden_at' => $card->hidden_at?->toIso8601String(),
            'archived_at' => $card->archived_at?->toIso8601String(),
            'link_url' => $card->link_url,
            'sort_order' => $card->sort_order,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'section' => [$required, 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => [$required, 'string', 'max:255'],
            'body' => [$required, 'string'],
            'link_label' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
