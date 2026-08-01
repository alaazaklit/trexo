<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RefreshTokenService
{
    /**
     * Issues a new refresh token for the given user and persists only its
     * hash. Returns the plaintext token — the only time it ever exists
     * outside the client's own storage, since it can't be recovered from
     * the hash afterward.
     */
    public function issue(User $user): string
    {
        $plainToken = Str::random(64);

        $user->refreshTokens()->create([
            'token_hash' => $this->hash($plainToken),
            'expires_at' => Carbon::now()->addDays((int) config('auth_tokens.refresh_ttl_days')),
        ]);

        return $plainToken;
    }

    /**
     * Looks up a plaintext refresh token and returns its record only if
     * it's still valid (exists, not revoked, not expired). Returns null on
     * any invalid/unknown/revoked/expired token so callers can't tell
     * those cases apart (nothing to be gained by distinguishing them, and
     * doing so would just add ways for the check to be gotten wrong).
     */
    public function resolve(string $plainToken): ?RefreshToken
    {
        $record = RefreshToken::where('token_hash', $this->hash($plainToken))->first();

        if (!$record || !$record->isActive()) {
            return null;
        }

        return $record;
    }

    public function revoke(RefreshToken $refreshToken): void
    {
        $refreshToken->update(['revoked_at' => Carbon::now()]);
    }

    public function revokeAllForUser(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => Carbon::now()]);
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
