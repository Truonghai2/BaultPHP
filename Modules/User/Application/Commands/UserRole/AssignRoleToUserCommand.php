<?php

namespace Modules\User\Application\Commands\UserRole;

/**
 * Command to assign a role to a user.
 */
class AssignRoleToUserCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly int $roleId,
        public readonly string $contextLevel,
        public readonly int $instanceId,
    ) {
    }
}
