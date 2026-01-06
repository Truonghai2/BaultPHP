<?php

namespace Modules\User\Application\CommandHandlers\User;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Application\Commands\User\AssignRoleCommand;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

/**
 * AssignRoleHandler
 *
 * Handles the AssignRoleCommand.
 */
class AssignRoleHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof AssignRoleCommand) {
            throw new \InvalidArgumentException('AssignRoleHandler can only handle AssignRoleCommand.');
        }

        $user = User::find($command->userId);
        if (!$user) {
            throw new \Exception("User with ID {$command->userId} not found");
        }

        $role = Role::find($command->roleId);
        if (!$role) {
            throw new \Exception("Role with ID {$command->roleId} not found");
        }

        $existing = RoleAssignment::where('user_id', '=', $command->userId)
            ->where('role_id', '=', $command->roleId)
            ->where('context_id', '=', $command->contextId)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $assignment = RoleAssignment::create([
            'user_id' => $command->userId,
            'role_id' => $command->roleId,
            'context_id' => $command->contextId,
        ]);

        Audit::security(
            "Role '{$role->name}' assigned to user '{$user->email}'",
            [
                'user_id' => $command->userId,
                'role_id' => $command->roleId,
                'role_name' => $role->name,
                'context_id' => $command->contextId,
                'action' => 'role_assigned',
            ],
        );

        return $assignment->id;
    }
}
