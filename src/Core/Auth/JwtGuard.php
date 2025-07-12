<?php

namespace Core\Auth;

use Modules\User\Infrastructure\Models\User;

class JwtGuard
{
    protected static ?User $userCache = null;

    public static function user(): ?User
    {
        if (self::$userCache) return self::$userCache;

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) return null;

        $token = substr($authHeader, 7);
        $payload = JWT::decode($token, env('JWT_SECRET', 'secret'));

        if (!$payload || !isset($payload['sub'])) return null;

        $user = User::find($payload['sub']);
        self::$userCache = $user;
        return $user;
    }
}
