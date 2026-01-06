<?php

namespace Modules\User\Application\CommandHandlers\Permission;

use Core\CQRS\Contracts\CommandInterface;
use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\Support\Facades\Audit;
use Modules\User\Application\Commands\Permission\UpdatePermissionCommand;
use Modules\User\Infrastructure\Models\Permission;

/**
 * UpdatePermissionHandler
 * 
 * Handles the UpdatePermissionCommand.
 */
class UpdatePermissionHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof UpdatePermissionCommand) {
            throw new \InvalidArgumentException('UpdatePermissionHandler can only handle UpdatePermissionCommand.');
        }

        $permission = Permission::find($command->permissionId);

        if (!$permission) {
            throw new \Exception("Permission with ID {$command->permissionId} not found");
        }

        // Update fields
        if ($command->name !== null) {
            // Check uniqueness
            $exists = Permission::where('name', '=', $command->name)
                ->where('id', '!=', $command->permissionId)
                ->exists();

            if ($exists) {
                throw new \Exception("Permission name '{$command->name}' already exists");
            }

            $permission->name = $command->name;
        }

        if ($command->description !== null) {
            $permission->description = $command->description;
        }

        if ($command->captype !== null) {
            if (!in_array($command->captype, ['read', 'write'])) {
                throw new \Exception("Invalid captype. Must be 'read' or 'write'");
            }
            $permission->captype = $command->captype;
        }

        $permission->save();

        // Audit log (update is auto-logged)
        Audit::log(
            'data_change',
            "Permission updated: {$permission->name}",
            [
                'permission_id' => $permission->id,
                'action' => 'permission_updated'
            ],
            'info'
        );

        return true;
    }
}
