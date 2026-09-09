<?php

namespace ShambaTrack\Core;

use ShambaTrack\Models\AuthSession;

class Auth
{
    private const COOKIE_NAME = 'st_session';

    public static function setSessionCookie(string $rawToken): void
    {
        setcookie(self::COOKIE_NAME, $rawToken, [
            'expires'  => time() + SESSION_TOKEN_TTL_DAYS * 86400,
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearSessionCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function rawTokenFromCookie(): ?string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }

    /** Returns the authenticated user row (with user_id key) or null. */
    public static function currentUser(): ?array
    {
        $token = self::rawTokenFromCookie();
        if (!$token) {
            return null;
        }
        return AuthSession::validate($token);
    }

    /** Halts the request with 401 if no valid session is present. */
    public static function requireUser(): array
    {
        $user = self::currentUser();
        if (!$user) {
            \ShambaTrack\Core\Http\Response::error('Not authenticated', 401);
        }
        return $user;
    }
}
