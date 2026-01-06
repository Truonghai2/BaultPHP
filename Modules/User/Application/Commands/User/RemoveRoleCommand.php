<?php

namespace Modules\User\Application\Commands\User;

use Core\CQRS\Contracts\CommandInterface;

/**
 * RemoveRoleCommand
 *
 * Command to remove a role from a user.
 */
class RemoveRoleCommand implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly int $roleId,
        public readonly ?int $contextId = null,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.user.remove_role';
    }
}
