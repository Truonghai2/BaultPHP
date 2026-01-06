<?php

namespace Modules\User\Application\CommandHandlers\User;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Application\Commands\User\RemoveRoleCommand;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

/**
 * RemoveRoleHandler
 *
 * Handles the RemoveRoleCommand.
 */
class RemoveRoleHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof RemoveRoleCommand) {
            throw new \InvalidArgumentException('RemoveRoleHandler can only handle RemoveRoleCommand.');
        }

        // Find the assignment
        $assignment = RoleAssignment::where('user_id', '=', $command->userId)
            ->where('role_id', '=', $command->roleId)
            ->where('context_id', '=', $command->contextId)
            ->first();

        if (!$assignment) {
            return true; // Already removed
        }

        // Get user and role for audit
        $user = User::find($command->userId);
        $role = Role::find($command->roleId);

        // Delete assignment
        $assignment->delete();

        // Security audit (deletion is auto-logged)
        Audit::security(
            "Role '{$role->name}' removed from user '{$user->email}'",
            [
                'user_id' => $command->userId,
                'role_id' => $command->roleId,
                'role_name' => $role->name,
                'context_id' => $command->contextId,
                'action' => 'role_removed',
            ],
        );

        return true;
    }
}
