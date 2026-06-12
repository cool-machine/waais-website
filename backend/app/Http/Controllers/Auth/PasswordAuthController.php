<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyRegistrationEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Traditional email + password registration and sign-in.
 *
 * Registration creates the account only. The membership application
 * form is unlocked after the applicant verifies their email address;
 * submission then goes through the regular application endpoints.
 */
class PasswordAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.unique' => 'An account with this email already exists. Sign in instead, or continue with Google.',
        ]);

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => mb_strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'approval_status' => ApprovalStatus::Draft,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        $this->sendVerificationEmail($user);

        return response()->json([
            'ok' => true,
            'verification_required' => true,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = mb_strtolower($credentials['email']);

        $existing = User::query()->where('email', $credentials['email'])->first();
        if ($existing !== null && $existing->password === null) {
            throw ValidationException::withMessages([
                'email' => 'This account uses Google sign-in. Use "Continue with Google" instead.',
            ]);
        }

        if (! Auth::guard('web')->attempt($credentials, remember: true)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Resume an interrupted Discourse SSO handshake (user clicked
        // "Log In" on the forum and was sent here to authenticate).
        $redirect = null;
        $user = Auth::guard('web')->user();
        if ($request->hasSession() && $user->canAccessMemberAreas()) {
            $intended = $request->session()->pull('discourse.sso.intended_url');
            if (is_string($intended) && $intended !== '') {
                $redirect = $intended;
            }
        }

        return response()->json([
            'ok' => true,
            'email_verified' => $user->email_verified_at !== null,
            'redirect' => $redirect,
        ]);
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $user->save();
        }

        Auth::login($user, remember: true);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/membership');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', mb_strtolower($validated['email']))->first();

        if ($user !== null && $user->email_verified_at === null && $user->password !== null) {
            $this->sendVerificationEmail($user);
        }

        // Always OK to avoid account enumeration.
        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Fire-and-forget; identical response whether or not the account
        // exists, to avoid account enumeration. Google-only accounts may
        // also reset: this simply adds a password alongside Google sign-in.
        PasswordBroker::sendResetLink(['email' => mb_strtolower($validated['email'])]);

        return response()->json(['ok' => true]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $validated['email'] = mb_strtolower($validated['email']);

        $status = PasswordBroker::reset($validated, function (User $user, string $password): void {
            $user->password = Hash::make($password);
            $user->save();
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function sendVerificationEmail(User $user): void
    {
        $link = URL::temporarySignedRoute(
            'auth.register.verify',
            now()->addHours(48),
            ['user' => $user->id],
        );

        $user->notify(new VerifyRegistrationEmail($link));
    }
}
