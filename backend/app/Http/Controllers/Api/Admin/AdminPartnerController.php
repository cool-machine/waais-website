<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminPartnerController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'content_status' => ['nullable', Rule::enum(ContentStatus::class)],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = Partner::query()
            ->with('creator:id,name,email')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');

        if (! empty($validated['content_status'])) {
            $query->where('content_status', $validated['content_status']);
        }
        if (! empty($validated['visibility'])) {
            $query->where('visibility', $validated['visibility']);
        }

        return $query->paginate($validated['per_page'] ?? 25);
    }

    public function show(Partner $partner): JsonResponse
    {
        $partner->load('creator:id,name,email');

        return response()->json(['data' => $partner]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $partner = DB::transaction(function () use ($validated, $request) {
            $partner = new Partner($validated);
            $partner->created_by = $request->user()->id;
            $partner->content_status = ContentStatus::Draft;
            $partner->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'partners.create',
                'auditable_type' => Partner::class,
                'auditable_id' => $partner->id,
                'old_values' => null,
                'new_values' => ['partner' => $this->snapshot($partner)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $partner;
        });

        return response()->json(['data' => $partner->fresh()->load('creator:id,name,email')], 201);
    }

    public function update(Request $request, Partner $partner): JsonResponse
    {
        $validated = $request->validate($this->rules(updating: true));

        DB::transaction(function () use ($validated, $partner, $request): void {
            $before = $this->snapshot($partner);

            $partner->fill($validated);
            $partner->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'partners.update',
                'auditable_type' => Partner::class,
                'auditable_id' => $partner->id,
                'old_values' => ['partner' => $before],
                'new_values' => ['partner' => $this->snapshot($partner)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $partner->fresh()->load('creator:id,name,email')]);
    }

    public function publish(Request $request, Partner $partner): JsonResponse
    {
        return $this->transitionContentStatus($request, $partner, [
            'content_status' => ContentStatus::Published,
            'timestamp_field' => 'published_at',
            'action' => 'partners.publish',
        ]);
    }

    public function hide(Request $request, Partner $partner): JsonResponse
    {
        return $this->transitionContentStatus($request, $partner, [
            'content_status' => ContentStatus::Hidden,
            'timestamp_field' => 'hidden_at',
            'action' => 'partners.hide',
        ]);
    }

    public function archive(Request $request, Partner $partner): JsonResponse
    {
        return $this->transitionContentStatus($request, $partner, [
            'content_status' => ContentStatus::Archived,
            'timestamp_field' => 'archived_at',
            'action' => 'partners.archive',
        ]);
    }

    /**
     * @param  array{ content_status: ContentStatus, timestamp_field: string, action: string }  $config
     */
    private function transitionContentStatus(Request $request, Partner $partner, array $config): JsonResponse
    {
        DB::transaction(function () use ($partner, $config, $request): void {
            $before = $this->snapshot($partner);

            $partner->content_status = $config['content_status'];
            $partner->{$config['timestamp_field']} = now();
            $partner->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => $config['action'],
                'auditable_type' => Partner::class,
                'auditable_id' => $partner->id,
                'old_values' => ['partner' => $before],
                'new_values' => ['partner' => $this->snapshot($partner)],
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return response()->json(['data' => $partner->fresh()->load('creator:id,name,email')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Partner $partner): array
    {
        return [
            'name' => $partner->name,
            'partner_type' => $partner->partner_type,
            'content_status' => $partner->content_status?->value,
            'visibility' => $partner->visibility?->value,
            'published_at' => $partner->published_at?->toIso8601String(),
            'hidden_at' => $partner->hidden_at?->toIso8601String(),
            'archived_at' => $partner->archived_at?->toIso8601String(),
            'website_url' => $partner->website_url,
            'sort_order' => $partner->sort_order,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'partner_type' => ['nullable', 'string', 'max:255'],
            'summary' => [$required, 'string'],
            'description' => [$required, 'string'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
