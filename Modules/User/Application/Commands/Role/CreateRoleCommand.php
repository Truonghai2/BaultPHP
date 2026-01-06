<?php

namespace Modules\User\Application\Commands\Role;

use Core\CQRS\Contracts\CommandInterface;

/**
 * CreateRoleCommand
 *
 * Command to create a new role.
 */
class CreateRoleCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $permissionIds = [],
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.role.create';
    }
}
