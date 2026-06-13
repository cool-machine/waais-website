<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind a per-area admin permission. Usage: `area:events`,
 * `area:partners`, or `area:startups`. Super admins pass every area; an
 * admin passes only the areas they have been granted.
 */
class EnsureAreaAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();

        $allowed = match ($area) {
            'events' => (bool) $user?->canManageEvents(),
            'partners' => (bool) $user?->canManagePartners(),
            'startups' => (bool) $user?->canManageStartups(),
            default => false,
        };

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
