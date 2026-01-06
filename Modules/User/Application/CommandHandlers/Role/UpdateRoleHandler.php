<?php

namespace Modules\User\Application\CommandHandlers\Role;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Application\Commands\Role\UpdateRoleCommand;
use Modules\User\Infrastructure\Models\Role;

/**
 * UpdateRoleHandler
 *
 * Handles the UpdateRoleCommand.
 */
class UpdateRoleHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        if (!$command instanceof UpdateRoleCommand) {
            throw new \InvalidArgumentException('UpdateRoleHandler can only handle UpdateRoleCommand.');
        }

        $role = Role::find($command->roleId);

        if (!$role) {
            throw new \Exception("Role with ID {$command->roleId} not found");
        }

        if ($command->name !== null) {
            $exists = Role::where('name', '=', $command->name)
                ->where('id', '!=', $command->roleId)
                ->exists();

            if ($exists) {
                throw new \Exception("Role name '{$command->name}' already exists");
            }

            $role->name = $command->name;
        }

        if ($command->description !== null) {
            $role->description = $command->description;
        }

        $role->save();

        Audit::log(
            'data_change',
            "Role updated: {$role->name}",
            [
                'role_id' => $role->id,
                'action' => 'role_updated',
            ],
            'info',
        );

        return true;
    }
}
