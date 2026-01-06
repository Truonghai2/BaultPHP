<?php

namespace Modules\User\Application\CommandHandlers\Role;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\User\Infrastructure\Models\Role;

/**
 * CreateRoleHandler
 *
 * Handles the CreateRoleCommand.
 */
class CreateRoleHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): int
    {
        if (Role::where('name', '=', $command->name)->exists()) {
            throw new \Exception("Role '{$command->name}' already exists");
        }

        $role = Role::create([
            'name' => $command->name,
            'description' => $command->description,
        ]);

        if (!empty($command->permissionIds)) {
            $role->permissions()->sync($command->permissionIds);
        }

        Audit::log(
            'data_change',
            "Role created: {$command->name}",
            [
                'role_id' => $role->id,
                'name' => $command->name,
                'permission_count' => count($command->permissionIds),
                'action' => 'role_created',
            ],
            'info',
        );

        return $role->id;
    }
}
