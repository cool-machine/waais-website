<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EmailSignInLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class EmailAuthController extends Controller
{
    public function sendLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'next' => ['nullable', 'string'],
        ]);

        $email = mb_strtolower($validated['email']);
        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill([
                'name' => $email,
                'approval_status' => ApprovalStatus::Submitted,
                'permission_role' => PermissionRole::PendingUser,
            ]);
        } elseif (in_array($user->approval_status, [ApprovalStatus::None, ApprovalStatus::Draft], true)) {
            $user->approval_status = ApprovalStatus::Submitted;
            $user->permission_role = PermissionRole::PendingUser;
        }

        $user->save();

        $link = URL::temporarySignedRoute(
            'auth.email.callback',
            now()->addMinutes(30),
            [
                'user' => $user->id,
                'next' => $this->intendedFrontendPath($validated['next'] ?? null) ?? '/membership',
            ],
        );

        $user->notify(new EmailSignInLink($link));

        return response()->json(['ok' => true]);
    }

    public function callback(Request $request, User $user): RedirectResponse
    {
        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        Auth::login($user, remember: true);

        $path = $this->intendedFrontendPath($request->query('next'))
            ?? ($user->canAccessMemberAreas() ? '/app/dashboard' : '/membership');

        return redirect()->away($this->frontendUrl($path));
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }

    private function intendedFrontendPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return $path;
    }
}
