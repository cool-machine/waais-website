<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminTeamMemberController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $validated = $request->validate([
            'content_status' => ['nullable', Rule::enum(ContentStatus::class)],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = TeamMember::query()
            ->with('creator:id,name,email')
            ->orderByRaw("CASE WHEN member_group = 'founder' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');

        if (! empty($validated['content_status'])) {
            $query->where('content_status', $validated['content_status']);
        }
        if (! empty($validated['visibility'])) {
            $query->where('visibility', $validated['visibility']);
        }

        return $query->paginate($validated['per_page'] ?? 50);
    }

    public function show(TeamMember $teamMember): JsonResponse
    {
        $teamMember->load('creator:id,name,email');

        return response()->json(['data' => $teamMember]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $member = DB::transaction(function () use ($validated, $request) {
            $member = new TeamMember($validated);
            $member->created_by = $request->user()->id;
            $member->content_status = ContentStatus::Draft;
            $member->save();

            $this->audit($request, 'team_members.create', $member, null);

            return $member;
        });

        return response()->json(['data' => $member->fresh()->load('creator:id,name,email')], 201);
    }

    public function update(Request $request, TeamMember $teamMember): JsonResponse
    {
        $validated = $request->validate($this->rules(updating: true));

        DB::transaction(function () use ($validated, $teamMember, $request): void {
            $before = $this->snapshot($teamMember);
            $teamMember->fill($validated);
            $teamMember->save();
            $this->audit($request, 'team_members.update', $teamMember, $before);
        });

        return response()->json(['data' => $teamMember->fresh()->load('creator:id,name,email')]);
    }

    public function publish(Request $request, TeamMember $teamMember): JsonResponse
    {
        return $this->transition($request, $teamMember, ContentStatus::Published, 'published_at', 'team_members.publish');
    }

    public function hide(Request $request, TeamMember $teamMember): JsonResponse
    {
        return $this->transition($request, $teamMember, ContentStatus::Hidden, 'hidden_at', 'team_members.hide');
    }

    public function archive(Request $request, TeamMember $teamMember): JsonResponse
    {
        return $this->transition($request, $teamMember, ContentStatus::Archived, 'archived_at', 'team_members.archive');
    }

    private function transition(Request $request, TeamMember $member, ContentStatus $status, string $field, string $action): JsonResponse
    {
        DB::transaction(function () use ($member, $status, $field, $action, $request): void {
            $before = $this->snapshot($member);
            $member->content_status = $status;
            $member->{$field} = now();
            $member->save();
            $this->audit($request, $action, $member, $before);
        });

        return response()->json(['data' => $member->fresh()->load('creator:id,name,email')]);
    }

    private function audit(Request $request, string $action, TeamMember $member, ?array $before): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => TeamMember::class,
            'auditable_id' => $member->id,
            'old_values' => $before ? ['team_member' => $before] : null,
            'new_values' => ['team_member' => $this->snapshot($member)],
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TeamMember $member): array
    {
        return [
            'name' => $member->name,
            'role_title' => $member->role_title,
            'member_group' => $member->member_group,
            'content_status' => $member->content_status?->value,
            'visibility' => $member->visibility?->value,
            'published_at' => $member->published_at?->toIso8601String(),
            'sort_order' => $member->sort_order,
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
            'role_title' => ['nullable', 'string', 'max:255'],
            'member_group' => ['nullable', Rule::in(['founder', 'advisor'])],
            'bio' => ['nullable', 'string', 'max:2000'],
            // Allow a hosted URL or a site-relative path like /team/jane.jpg.
            'photo_url' => ['nullable', 'string', 'max:500'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'visibility' => ['nullable', Rule::enum(ContentVisibility::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
