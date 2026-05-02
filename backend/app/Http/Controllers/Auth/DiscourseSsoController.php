<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DiscourseSsoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $payload = $request->query('sso');
        $signature = $request->query('sig');

        abort_unless(is_string($payload) && is_string($signature), Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->verifySignature($payload, $signature);

        $params = $this->decodePayload($payload);
        $returnUrl = $this->validatedReturnUrl($params['return_sso_url'] ?? null);

        if (! Auth::check()) {
            $request->session()->put('discourse.sso.intended_url', $request->fullUrl());

            return redirect()->away($this->frontendUrl('/app/sign-in'));
        }

        /** @var User $user */
        $user = $request->user();
        if (! $user->canAccessMemberAreas()) {
            return redirect()->away($this->frontendUrl('/app/pending'));
        }

        $responsePayload = $this->responsePayload($user, (string) $params['nonce']);
        $encoded = base64_encode(http_build_query($responsePayload, '', '&', PHP_QUERY_RFC3986));
        $responseSignature = hash_hmac('sha256', $encoded, $this->secret());

        return redirect()->away($returnUrl.'?'.http_build_query([
            'sso' => $encoded,
            'sig' => $responseSignature,
        ], '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * @return array<string, string>
     */
    private function decodePayload(string $payload): array
    {
        $decoded = base64_decode($payload, strict: true);
        abort_if($decoded === false, Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid Discourse SSO payload.');

        parse_str($decoded, $params);
        abort_unless(
            isset($params['nonce'], $params['return_sso_url'])
            && is_string($params['nonce'])
            && is_string($params['return_sso_url'])
            && $params['nonce'] !== '',
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        return $params;
    }

    private function verifySignature(string $payload, string $signature): void
    {
        abort_unless(
            hash_equals(hash_hmac('sha256', $payload, $this->secret()), $signature),
            Response::HTTP_FORBIDDEN
        );
    }

    private function validatedReturnUrl(mixed $returnUrl): string
    {
        abort_unless(is_string($returnUrl) && filter_var($returnUrl, FILTER_VALIDATE_URL), Response::HTTP_UNPROCESSABLE_ENTITY);

        $expectedHost = parse_url((string) config('services.discourse.url'), PHP_URL_HOST);
        $actualHost = parse_url($returnUrl, PHP_URL_HOST);
        abort_unless($expectedHost !== null && $actualHost === $expectedHost, Response::HTTP_UNPROCESSABLE_ENTITY);

        return $returnUrl;
    }

    /**
     * @return array<string, string>
     */
    private function responsePayload(User $user, string $nonce): array
    {
        $payload = [
            'nonce' => $nonce,
            'email' => $user->email,
            'external_id' => 'waais-user-'.$user->id,
            'name' => $user->display_name ?: $user->name ?: $user->email,
            'username' => $this->username($user),
            'require_activation' => $user->email_verified_at ? 'false' : 'true',
            'moderator' => $user->isAdmin() ? 'true' : 'false',
            'admin' => $user->isSuperAdmin() ? 'true' : 'false',
            'groups' => $user->isAdmin() ? 'waais_members,waais_admins' : 'waais_members',
        ];

        if ($user->avatar_url) {
            $payload['avatar_url'] = $user->avatar_url;
        }

        return $payload;
    }

    private function username(User $user): string
    {
        $base = $user->display_name ?: $user->name ?: strstr($user->email, '@', before_needle: true) ?: 'member';
        $username = strtolower((string) preg_replace('/[^A-Za-z0-9_]+/', '_', $base));
        $username = trim($username, '_');

        return $username !== '' ? substr($username, 0, 40) : 'member_'.$user->id;
    }

    private function secret(): string
    {
        $secret = config('services.discourse.connect_secret');
        abort_unless(is_string($secret) && $secret !== '', Response::HTTP_SERVICE_UNAVAILABLE);

        return $secret;
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
