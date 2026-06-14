<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PublicTeamMemberController extends Controller
{
    public function index(): JsonResponse
    {
        $members = $this->publicQuery()
            // Founders before advisors, then by manual sort order.
            ->orderByRaw("CASE WHEN member_group = 'founder' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TeamMember $member) => $this->project($member));

        return response()->json(['data' => $members]);
    }

    /**
     * Anything not (Published) and (Public OR Mixed) is treated as if it
     * does not exist.
     */
    private function publicQuery(): Builder
    {
        return TeamMember::query()
            ->where('content_status', ContentStatus::Published)
            ->whereIn('visibility', [ContentVisibility::Public, ContentVisibility::Mixed]);
    }

    /**
     * @return array<string, mixed>
     */
    private function project(TeamMember $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'role_title' => $member->role_title,
            'member_group' => $member->member_group,
            'bio' => $member->bio,
            'photo_url' => $member->photo_url,
            'linkedin_url' => $member->linkedin_url,
            'sort_order' => $member->sort_order,
        ];
    }
}
