<?php

namespace Modules\User\Application\Commands\Role;

use Core\CQRS\Contracts\CommandInterface;

/**
 * DeleteRoleCommand
 *
 * Command to delete a role.
 */
class DeleteRoleCommand implements CommandInterface
{
    public function __construct(
        public readonly int $roleId,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.role.delete';
    }
}
