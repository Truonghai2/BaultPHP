<?php

namespace Modules\User\Application\Listeners;

use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Domain\Services\AccessControlService;

/**
 * Lắng nghe sự kiện gán vai trò và xóa cache quyền của người dùng tương ứng.
 */
class ClearPermissionCacheListener
{
    public function __construct(private AccessControlService $acl)
    {
    }

    public function handle(RoleAssignedToUser $event): void
    {
        $this->acl->flushCacheForUser($event->user->id);
    }
}
