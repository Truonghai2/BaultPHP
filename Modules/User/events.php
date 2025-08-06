<?php

use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Domain\Events\RolePermissionsChanged;
use Modules\User\Listeners\FlushUserPermissionCache;

/**
 * Event-listener mappings for the User module.
 */
return [
    RoleAssignedToUser::class => [
        FlushUserPermissionCache::class,
    ],
    RolePermissionsChanged::class => [
        FlushUserPermissionCache::class,
    ],
];
