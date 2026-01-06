<?php

namespace Modules\User\Application\CommandHandlers\Permission;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Infrastructure\Models\Permission;

/**
 * CreatePermissionHandler
 * 
 * Handles the CreatePermissionCommand.
 */
class CreatePermissionHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): int
    {
        // Check if permission already exists
        if (Permission::where('name', '=', $command->name)->exists()) {
            throw new \Exception("Permission '{$command->name}' already exists");
        }

        // Validate captype
        if (!in_array($command->captype, ['read', 'write'])) {
            throw new \Exception("Invalid captype. Must be 'read' or 'write'");
        }

        // Create permission
        $permission = Permission::create([
            'name' => $command->name,
            'description' => $command->description,
            'captype' => $command->captype
        ]);

        // Audit log (creation is auto-logged)
        Audit::log(
            'data_change',
            "Permission created: {$command->name}",
            [
                'permission_id' => $permission->id,
                'name' => $command->name,
                'captype' => $command->captype,
                'action' => 'permission_created'
            ],
            'info'
        );

        return $permission->id;
    }
}

