<?php

namespace Modules\User\Http\Controllers\Admin;

use Http\JsonResponse;
use InvalidArgumentException;
use Modules\User\Application\Commands\User\AssignRoleCommand;
use Modules\User\Application\CommandHandlers\User\AssignRoleHandler;
use Modules\User\Domain\Exceptions\RoleNotFoundException;
use Modules\User\Domain\Exceptions\UserNotFoundException;
use Modules\User\Http\Requests\AssignRoleRequest;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface;

class UserRoleController
{
    public function __construct(
        private AssignRoleHandler $assignRoleHandler,
    ) {
    }

    /**
     * Assign a role to a user in a specific context.
     */
    public function assign(AssignRoleRequest $request, User $user): ResponseInterface
    {
        $validated = $request->validated();

        try {
            $command = new AssignRoleCommand(
                userId: $user->id,
                roleId: (int)$validated['role_id'],
                contextLevel: $validated['context_level'],
                instanceId: (int)$validated['instance_id'],
            );

            $this->assignRoleHandler->handle($command);

            return new JsonResponse([
                'message' => 'Role assignment command executed successfully.',
            ]);
        } catch (RoleNotFoundException | UserNotFoundException | InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
