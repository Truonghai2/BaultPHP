<?php

namespace Modules\User\Application\Commands\Permission;

use Core\CQRS\Contracts\CommandInterface;

/**
 * CreatePermissionCommand
 *
 * Command to create a new permission.
 */
class CreatePermissionCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $captype = 'read',
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.permission.create';
    }
}
