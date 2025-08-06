<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Modules\User\Domain\Events\UserDeleted;
use Modules\User\Domain\Services\PermissionCacheService;

class FlushUserPermissionCache
{
    public function __construct(
        private readonly PermissionCacheService $permissionCache,
    ) {
    }

    public function handle(UserDeleted $event): void
    {
        $this->permissionCache->flushForUserId($event->deletedUserId);
    }
}
