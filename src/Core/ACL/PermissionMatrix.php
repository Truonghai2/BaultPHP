<?php

namespace Core\ACL;

use Illuminate\Support\Facades\Cache;

class PermissionMatrix
{
    public function can(string $userId, string $permission, ?int $contextId = null): bool
    {
        return Cache::get("perm:{$userId}:{$permission}:{$contextId}", false);
    }

    public function grant(string $userId, string $permission, ?int $contextId = null): void
    {
        Cache::put("perm:{$userId}:{$permission}:{$contextId}", true);
    }
}
