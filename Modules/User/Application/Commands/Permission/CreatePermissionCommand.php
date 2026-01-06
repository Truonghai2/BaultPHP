<?php

namespace Modules\User\Application\Commands\Permission;

use Core\CQRS\Contracts\CommandInterface;

/**
 * CreatePermissionCommand
 *
 * Command to create a new permission.
 *
 * @property-read string $name
 * @property-read string $description
 * @property-read string $captype
 * @property-read int $permissionId
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
