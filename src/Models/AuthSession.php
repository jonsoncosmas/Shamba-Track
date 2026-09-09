<?php

namespace ShambaTrack\Models;

use ShambaTrack\Core\Database;

class AuthSession
{
    /** Create a new session, return the RAW token (only ever available here — store the hash). */
    public static function create(int $userId, ?string $deviceLabel = null): string
    {
        $pdo = Database::connect();

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TOKEN_TTL_DAYS * 86400);

        $stmt = $pdo->prepare(
            'INSERT INTO auth_sessions (user_id, token_hash, device_label, expires_at)
             VALUES (:user_id, :token_hash, :device_label, :expires_at)'
        );
        $stmt->execute([
            'user_id'      => $userId,
            'token_hash'   => $tokenHash,
            'device_label' => $deviceLabel,
            'expires_at'   => $expiresAt,
        ]);

        return $rawToken;
    }

    /** Validate a raw token from the client cookie. Returns the user row, or null. */
    public static function validate(string $rawToken): ?array
    {
        $pdo = Database::connect();
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $pdo->prepare(
            'SELECT s.*, u.id AS user_id, u.phone_number, u.full_name, u.preferred_language, u.role
             FROM auth_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = :hash
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['hash' => $tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Sliding expiration: touch last_used_at so active devices don't get logged out
        $pdo->prepare('UPDATE auth_sessions SET last_used_at = NOW() WHERE id = :id')
            ->execute(['id' => $row['id']]);

        return $row;
    }

    public static function revokeByToken(string $rawToken): void
    {
        $pdo = Database::connect();
        $tokenHash = hash('sha256', $rawToken);
        $pdo->prepare('UPDATE auth_sessions SET revoked_at = NOW() WHERE token_hash = :hash')
            ->execute(['hash' => $tokenHash]);
    }
}
