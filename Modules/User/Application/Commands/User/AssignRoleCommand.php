<?php

namespace Modules\User\Application\Commands\User;

use Core\CQRS\Contracts\CommandInterface;

/**
 * AssignRoleCommand
 *
 * Command to assign a role to a user in a specific context.
 *
 * @property-read int $userId
 * @property-read int $roleId
 * @property-read int|null $contextId
 * @property-read int|null $contextLevel
 * @property-read int|null $instanceId
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
