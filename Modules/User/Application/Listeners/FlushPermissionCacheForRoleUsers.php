<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Core\Support\Facades\Cache;
use Modules\User\Domain\Events\RolePermissionsChanged;

/**
 * Handles the RolePermissionsChanged event to flush the permission cache
 * for all users associated with that role, using tagged caching for efficiency.
 */
class FlushPermissionCacheForRoleUsers
{
    public function handle(RolePermissionsChanged $event): void
    {
        Cache::tags('role:' . $event->role->id)->flush();
    }
}
