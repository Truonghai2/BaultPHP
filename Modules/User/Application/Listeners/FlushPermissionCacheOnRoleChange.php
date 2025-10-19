<?php

declare(strict_types=1);

namespace Modules\User\Application\Listeners;

use Core\Support\Facades\Cache;
use Modules\User\Domain\Events\RoleAssignedToUser;

class FlushPermissionCacheOnRoleChange
{
    public function handle(RoleAssignedToUser $event): void
    {
        Cache::tags('user:' . $event->user->id)->flush();
    }
}
