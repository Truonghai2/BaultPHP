<?php

namespace Modules\User\Application\CommandHandlers\Permission;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Infrastructure\Models\Permission;

/**
 * DeletePermissionHandler
 * 
 * Handles the DeletePermissionCommand.
 */
class DeletePermissionHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $permission = Permission::find($command->permissionId);

        if (!$permission) {
            throw new \Exception("Permission with ID {$command->permissionId} not found");
        }

        $permissionName = $permission->name;

        $roleCount = $permission->roles->count();
        if ($roleCount > 0) {
            throw new \Exception("Cannot delete permission '{$permissionName}'. It is assigned to {$roleCount} role(s)");
        }

        $permission->delete();

        Audit::log(
            'data_change',
            "Permission deleted: {$permissionName}",
            [
                'permission_id' => $command->permissionId,
                'permission_name' => $permissionName,
                'action' => 'permission_deleted'
            ],
            'warning'
        );

        return true;
    }
}

