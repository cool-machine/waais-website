<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleUserProvisioner
{
    public function provision(SocialiteUser $googleUser): User
    {
        $googleId = (string) $googleUser->getId();
        $email = $googleUser->getEmail();

        abort_if($googleId === '' || ! $email, 422, 'Google account did not provide a usable identity.');

        $user = User::query()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        $isNew = ! $user;
        $user ??= new User([
            'email' => $email,
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        abort_if(
            $user->google_id && $user->google_id !== $googleId,
            409,
            'This email is linked to a different Google account.'
        );

        $user->fill([
            'name' => $googleUser->getName() ?: $email,
            'email' => $email,
            'google_id' => $googleId,
            'avatar_url' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        $this->fillLegalNameFromGoogle($user, $googleUser);

        if (! $isNew && in_array($user->approval_status, [ApprovalStatus::None, ApprovalStatus::Draft], true)) {
            $user->approval_status = ApprovalStatus::Submitted;
            $user->permission_role = PermissionRole::PendingUser;
        }

        $user->save();

        return $user;
    }

    private function fillLegalNameFromGoogle(User $user, SocialiteUser $googleUser): void
    {
        $raw = $googleUser->getRaw();

        if (! $user->first_name && isset($raw['given_name'])) {
            $user->first_name = $raw['given_name'];
        }

        if (! $user->last_name && isset($raw['family_name'])) {
            $user->last_name = $raw['family_name'];
        }
    }
}
