<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(GoogleUserProvisioner $provisioner): RedirectResponse
    {
        $user = $provisioner->provision(Socialite::driver('google')->user());

        Auth::login($user, remember: true);

        return redirect()->away($this->frontendUrl($user->canAccessMemberAreas() ? '/app/dashboard' : '/app/pending'));
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
