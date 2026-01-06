<?php

namespace Modules\User\Application\Commands\Permission;

use Core\CQRS\Contracts\CommandInterface;

/**
 * DeletePermissionCommand
 * 
 * Command to delete a permission.
 */
class DeletePermissionCommand implements CommandInterface
{
    public function __construct(
        public readonly int $permissionId
    ) {}

    public function getCommandName(): string
    {
        return 'user.permission.delete';
    }
}


