<?php

namespace App\Support;

use App\Enums\AffiliationType;
use Illuminate\Validation\Rule;

/**
 * Validation rules for membership-application payloads, shared by the
 * authenticated application endpoints and the public registration flow.
 */
class MembershipApplicationRules
{
    public const PRIVACY_ACKNOWLEDGEMENT_VERSION = '2026-05-02';

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $requirePrivacyAcknowledgement = false): array
    {
        return [
            'affiliation_type' => ['nullable', Rule::enum(AffiliationType::class)],
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone_whatsapp' => ['nullable', 'string', 'max:255'],
            'is_alumnus' => ['required', 'boolean'],
            'school_affiliation' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'between:1800,2100'],
            'inviter_name' => ['nullable', 'string', 'max:255'],
            'primary_location' => ['nullable', 'string', 'max:255'],
            'secondary_location' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'experience_summary' => ['nullable', 'string'],
            'expertise_summary' => ['nullable', 'string'],
            'industries_to_add_value' => ['nullable', 'array'],
            'industries_to_add_value.*' => ['string', 'max:120'],
            'industries_to_extend_expertise' => ['nullable', 'array'],
            'industries_to_extend_expertise.*' => ['string', 'max:120'],
            'availability' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'between:13,120'],
            'privacy_acknowledgement' => [$requirePrivacyAcknowledgement ? 'accepted' : 'sometimes', 'boolean'],
        ];
    }
}
