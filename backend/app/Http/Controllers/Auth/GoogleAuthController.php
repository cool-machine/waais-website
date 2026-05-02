<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $next = $this->intendedFrontendPath($request->query('next'));
        if ($next !== null) {
            $request->session()->put('auth.intended_frontend_path', $next);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(GoogleUserProvisioner $provisioner): RedirectResponse
    {
        $user = $provisioner->provision(Socialite::driver('google')->user());

        Auth::login($user, remember: true);

        $path = session()->pull('auth.intended_frontend_path')
            ?? ($user->canAccessMemberAreas() ? '/app/dashboard' : '/app/pending');

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
