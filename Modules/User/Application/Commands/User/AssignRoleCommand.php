<?php

namespace Modules\User\Application\Commands\User;

use Core\CQRS\Contracts\CommandInterface;

/**
 * AssignRoleCommand
 *
 * Command to assign a role to a user in a specific context.
 */
class AssignRoleCommand implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly int $roleId,
        public readonly ?int $contextId = null,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.user.assign_role';
    }
}
