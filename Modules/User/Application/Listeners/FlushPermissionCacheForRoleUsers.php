<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Modules\User\Domain\Events\RolePermissionsChanged;
use Modules\User\Domain\Services\PermissionCacheService;

/**
 * Xử lý sự kiện RolePermissionsChanged để xóa cache quyền của TẤT CẢ
 * người dùng được gán vai trò đó.
 * Điều này đảm bảo rằng các thay đổi về quyền của vai trò được áp dụng
 * cho tất cả người dùng liên quan.
 */
class FlushPermissionCacheForRoleUsers
{
    public function __construct(private readonly PermissionCacheService $permissionCache)
    {
    }

    public function handle(RolePermissionsChanged $event): void
    {
        // Using chunkById is highly memory-efficient for roles with many users.
        // It processes users in batches (e.g., 200 at a time) instead of
        // loading all of them into memory at once.
        $event->role->users()->chunkById(
            200,
            function ($users) {
                foreach ($users as $user) {
                    // Use the service injected via the constructor.
                    $this->permissionCache->flushForUser($user);
                }
            },
        );
    }
}
