<?php

namespace Modules\User\Application\Commands\Role;

use Core\CQRS\Contracts\CommandInterface;

/**
 * AssignPermissionsCommand
 *
 * Command to assign permissions to a role.
 *
 * @property-read int $roleId
 * @property-read array $permissionIds
 */
class AssignPermissionsCommand implements CommandInterface
{
    public function __construct(
        public readonly int $roleId,
        public readonly array $permissionIds,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.role.assign_permissions';
    }
}
