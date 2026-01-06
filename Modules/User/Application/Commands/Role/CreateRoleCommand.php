<?php

namespace Modules\User\Application\Commands\Role;

use Core\CQRS\Contracts\CommandInterface;

/**
 * CreateRoleCommand
 *
 * Command to create a new role.
 *
 * @property-read string $name
 * @property-read string $description
 * @property-read array $permissionIds
 * @property-read int $roleId
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
