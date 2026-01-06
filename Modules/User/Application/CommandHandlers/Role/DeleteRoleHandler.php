<?php

namespace Modules\User\Application\CommandHandlers\Role;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Infrastructure\Models\Role;

/**
 * DeleteRoleHandler
 *
 * Handles the DeleteRoleCommand.
 */
class DeleteRoleHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $role = Role::find($command->roleId);

        if (!$role) {
            throw new \Exception("Role with ID {$command->roleId} not found");
        }

        $roleName = $role->name;

        $userCount = $role->users->count();
        if ($userCount > 0) {
            throw new \Exception("Cannot delete role '{$roleName}'. It is assigned to {$userCount} user(s)");
        }

        $role->delete();

        Audit::log(
            'data_change',
            "Role deleted: {$roleName}",
            [
                'role_id' => $command->roleId,
                'role_name' => $roleName,
                'action' => 'role_deleted',
            ],
            'warning',
        );

        return true;
    }
}
