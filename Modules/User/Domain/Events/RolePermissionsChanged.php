<?php

namespace Modules\User\Domain\Events;

use Modules\User\Infrastructure\Models\Role;

/**
 * Fired when the permissions for a specific role are updated.
 */
class RolePermissionsChanged
{
    public function __construct(public Role $role)
    {
    }
}
