<?php

namespace Modules\User\Application\Services;

use Core\Cache\CacheManager;
use Modules\User\Infrastructure\Models\User;

class PermissionCacheService
{
    public function __construct(private CacheManager $cache) {}

    /**
     * Xóa tất cả các quyền đã được cache cho một người dùng cụ thể.
     * Phương thức này nên được gọi bất cứ khi nào vai trò hoặc quyền của người dùng thay đổi.
     *
     * @param User $user
     * @return void
     */
    public function flushForUser(User $user): void
    {
        // With the new optimization, we only need to delete one key per user.
        // This is much more efficient than scanning for multiple keys.
        $cacheKey = "acl:all_perms:{$user->id}";
        $this->cache->del([$cacheKey]); // `del` can accept an array of keys
    }
}