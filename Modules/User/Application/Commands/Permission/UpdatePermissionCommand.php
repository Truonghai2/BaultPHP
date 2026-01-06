<?php

namespace Modules\User\Application\Commands\Permission;

use Core\CQRS\Contracts\CommandInterface;

/**
 * UpdatePermissionCommand
 *
 * Command to update a permission.
 *
 * @property-read int $permissionId
 * @property-read string|null $name
 * @property-read string|null $description
 * @property-read string|null $captype
 */
class UpdatePermissionCommand implements CommandInterface
{
    public function __construct(
        public readonly int $permissionId,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $captype = null,
    ) {
    }

    public function getCommandName(): string
    {
        return 'user.permission.update';
    }
}
