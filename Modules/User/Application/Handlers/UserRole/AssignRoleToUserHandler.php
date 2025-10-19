<?php

namespace Modules\User\Application\Handlers\UserRole;

use Core\Events\EventDispatcherInterface;
use Modules\User\Application\Commands\UserRole\AssignRoleToUserCommand;
use Modules\User\Domain\Events\RoleAssignedToUser;
use Modules\User\Domain\Exceptions\RoleNotFoundException;
use Modules\User\Domain\Exceptions\UserNotFoundException;
use Modules\User\Domain\Services\AccessControlService;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

/**
 * Handles the command to assign a role to a user.
 */
class AssignRoleToUserHandler
{
    public function __construct(
        private AccessControlService $acl,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws RoleNotFoundException
     * @throws UserNotFoundException
     * @throws \InvalidArgumentException
     */
    public function handle(AssignRoleToUserCommand $command): void
    {
        $user = User::find($command->userId);
        if (!$user) {
            throw new UserNotFoundException("User with ID {$command->userId} not found.");
        }

        $role = Role::find($command->roleId);
        if (!$role) {
            throw new RoleNotFoundException("Role with ID {$command->roleId} not found.");
        }

        $context = $this->acl->resolveContextByLevelAndId($command->contextLevel, $command->instanceId);

        RoleAssignment::upsert(
            [[
                'user_id'    => $user->id,
                'context_id' => $context->id,
                'role_id' => $role->id,
            ]],
            ['user_id', 'context_id'],
            ['role_id'],
        );

        $this->dispatcher->dispatch(new RoleAssignedToUser($user, $role, $context));
    }
}
