<?php

namespace Modules\User\Application\Commands\Role;

use Core\CQRS\Contracts\CommandInterface;

/**
 * UpdateRoleCommand
 *
 * Command to update a role.
 *
 * @property-read int $roleId
 * @property-read string|null $name
 * @property-read string|null $description
 */
class UpdateRoleCommand implements CommandInterface
{
    public function __construct(
        public readonly int $roleId,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.role.update';
    }
}
