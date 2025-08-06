<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Domain\Services\PermissionCacheService;

/**
 * Xử lý sự kiện RoleAssignedToUser để xóa cache quyền của người dùng.
 * Đây là bước cực kỳ quan trọng để đảm bảo các thay đổi về vai trò
 * có hiệu lực ngay lập tức.
 */
class FlushPermissionCacheOnRoleChange
{
    public function __construct(
        private readonly PermissionCacheService $permissionCache,
    ) {
    }

    public function handle(RoleAssignedToUser $event): void
    {
        $this->permissionCache->flushForUser($event->user);
    }
}
