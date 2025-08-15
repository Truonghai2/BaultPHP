<?php

namespace Modules\User\Listeners;

use Core\Contracts\Queue\ShouldQueue;
use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Domain\Events\RolePermissionsChanged;
use Modules\User\Domain\Services\PermissionCacheService;

class FlushUserPermissionCache implements ShouldQueue
{
    public function __construct(private PermissionCacheService $permissionCache)
    {
    }

    /**
     * Handle the incoming event.
     *
     * @param object $event
     * @return void
     */
    public function handle(object $event): void
    {
        if ($event instanceof RoleAssignedToUser) {
            // If a role is assigned, just flush the cache for that specific user.
            $this->permissionCache->flushForUser($event->user);
        }

        if ($event instanceof RolePermissionsChanged) {
            // This is the critical part: if a role's permissions change,
            // we must flush the cache for ALL users who have this role.

            // Using chunkById to process users in batches. This is much more
            // memory-efficient than loading all users at once with ->get().
            $event->role->users()->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $this->permissionCache->flushForUser($user);
                }
            });
        }
    }
}
