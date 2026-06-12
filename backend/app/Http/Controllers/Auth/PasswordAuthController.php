<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Http\Controllers\Controller;
use App\Models\ApplicationRevision;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\MembershipApplicationReceivedByAdmin;
use App\Notifications\MembershipApplicationSubmitted;
use App\Notifications\VerifyRegistrationEmail;
use App\Support\MembershipApplicationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Traditional email + password registration and sign-in.
 *
 * Registration is the combined account + membership-application form.
 * The application stays in Draft until the applicant verifies their
 * email; verification flips it to Submitted and notifies admins.
 */
class PasswordAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            ...MembershipApplicationRules::rules(requirePrivacyAcknowledgement: true),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.unique' => 'An account with this email already exists. Sign in instead, or continue with Google.',
        ]);

        $password = $validated['password'];
        unset($validated['password'], $validated['password_confirmation']);
        $privacyAcknowledged = (bool) ($validated['privacy_acknowledgement'] ?? false);
        unset($validated['privacy_acknowledgement']);

        $user = DB::transaction(function () use ($validated, $password, $privacyAcknowledged): User {
            $user = User::create([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => mb_strtolower($validated['email']),
                'password' => Hash::make($password),
                'approval_status' => ApprovalStatus::Draft,
                'permission_role' => PermissionRole::PendingUser,
                'affiliation_type' => $validated['affiliation_type'] ?? null,
            ]);

            $application = new MembershipApplication(['applicant_id' => $user->id]);
            $application->fill($validated);
            $application->approval_status = ApprovalStatus::Draft;
            if ($privacyAcknowledged) {
                $application->privacy_acknowledged_at = now();
                $application->privacy_acknowledgement_version = MembershipApplicationRules::PRIVACY_ACKNOWLEDGEMENT_VERSION;
            }
            $application->save();

            ApplicationRevision::create([
                'membership_application_id' => $application->id,
                'actor_id' => $user->id,
                'changed_fields' => array_keys($validated),
                'old_values' => [],
                'new_values' => $validated,
                'change_note' => 'registered',
            ]);

            return $user;
        });

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

        return response()->json([
            'ok' => true,
            'email_verified' => Auth::guard('web')->user()->email_verified_at !== null,
        ]);
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $application = $user->membershipApplications()->latest()->first();

        if ($application !== null && $application->approval_status === ApprovalStatus::Draft) {
            $application->approval_status = ApprovalStatus::Submitted;
            $application->submitted_at = now();
            $application->save();

            $user->approval_status = ApprovalStatus::Submitted;

            $user->notify(new MembershipApplicationSubmitted($application));
            $admins = User::admins()->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new MembershipApplicationReceivedByAdmin($application));
            }
        }

        $user->save();

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
