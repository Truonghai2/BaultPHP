<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Modules\User\Domain\Events\UserProfileUpdated;
use Modules\User\Domain\Services\PermissionCacheService;

/**
 * Xử lý sự kiện UserProfileUpdated để xóa cache quyền của người dùng.
 * Mặc dù việc cập nhật hồ sơ (tên, email) không trực tiếp thay đổi quyền,
 * nhưng đây là một thói quen tốt để đảm bảo tính nhất quán,
 * đặc biệt nếu các dữ liệu khác của người dùng được cache trong cùng cấu trúc.
 */
class FlushPermissionCacheOnProfileUpdate
{
    public function __construct(
        private readonly PermissionCacheService $permissionCache,
    ) {
    }

    public function handle(UserProfileUpdated $event): void
    {
        $this->permissionCache->flushForUserId($event->userId);
    }
}
