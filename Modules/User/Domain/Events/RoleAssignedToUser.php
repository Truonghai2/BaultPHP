<?php

namespace Modules\User\Domain\Events;

use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\User;

/**
 * Fired when a role is assigned to a user.
 */
class RoleAssignedToUser
{
    public function __construct(
        public User $user,
        public Role $role,
    ) {
    }
}
