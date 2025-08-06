<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Modules\User\Domain\Events\UserProfileUpdated;
use Modules\User\Domain\Services\PermissionCacheService;

class InvalidateUserPermissionCache
{
    public function __construct(
        private readonly PermissionCacheService $permissionCache,
    ) {
    }

    /**
     * Xử lý event UserProfileUpdated.
     */
    public function handle(UserProfileUpdated $event): void
    {
        $this->permissionCache->flushForUser($event->user);
    }
}
