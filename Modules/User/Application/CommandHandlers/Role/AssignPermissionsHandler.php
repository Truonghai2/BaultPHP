<?php

namespace Modules\User\Application\CommandHandlers\Role;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Domain\Events\RolePermissionsChanged;
use Modules\User\Infrastructure\Models\Permission;
use Modules\User\Infrastructure\Models\Role;

/**
 * AssignPermissionsHandler
 *
 * Handles the AssignPermissionsCommand.
 */
class AssignPermissionsHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $role = Role::find($command->roleId);

        if (!$role) {
            throw new \Exception("Role with ID {$command->roleId} not found");
        }

        foreach ($command->permissionIds as $permissionId) {
            if (!Permission::find($permissionId)) {
                throw new \Exception("Permission with ID {$permissionId} not found");
            }
        }

        $role->permissions()->sync($command->permissionIds);

        event(new RolePermissionsChanged($role, $command->permissionIds));

        Audit::log(
            'data_change',
            "Permissions assigned to role: {$role->name}",
            [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permission_ids' => $command->permissionIds,
                'permission_count' => count($command->permissionIds),
                'action' => 'permissions_assigned',
            ],
            'info',
        );

        return true;
    }
}
