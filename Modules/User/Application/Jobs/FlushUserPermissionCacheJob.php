<?php

declare(strict_types=1);

namespace Modules\User\Application\Jobs;

use Core\Queue\Job;
use Modules\User\Domain\Services\PermissionCacheService;

/**
 * Một Job để xóa cache quyền của một người dùng cụ thể một cách bất đồng bộ.
 */
class FlushUserPermissionCacheJob extends Job
{
    /**
     * @param int $userId ID của người dùng cần xóa cache.
     */
    public function __construct(public readonly int $userId)
    {
    }

    /**
     * Thực thi job.
     */
    public function handle(PermissionCacheService $permissionCache): void
    {
        $permissionCache->flushForUserId($this->userId);
    }
}
