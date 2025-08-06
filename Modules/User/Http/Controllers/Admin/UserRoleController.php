<?php

namespace Modules\User\Http\Controllers\Admin;

use Http\JsonResponse;
use InvalidArgumentException;
use Modules\User\Application\Commands\UserRole\AssignRoleToUserCommand;
use Modules\User\Application\Handlers\UserRole\AssignRoleToUserHandler;
use Modules\User\Domain\Exceptions\RoleNotFoundException;
use Modules\User\Domain\Exceptions\UserNotFoundException;
use Modules\User\Http\Requests\AssignRoleRequest;
use Modules\User\Infrastructure\Models\User;
use Psr\Http\Message\ResponseInterface;

class UserRoleController
{
    public function __construct(
        private AssignRoleToUserHandler $assignRoleHandler,
    ) {
    }

    /**
     * Gán một vai trò cho người dùng trong một context cụ thể.
     */
    public function assign(AssignRoleRequest $request, User $user): ResponseInterface
    {
        // 1. Validation is now handled automatically by AssignRoleRequest.
        // We can get the validated data directly.
        $validated = $request->validated();

        try {
            // 2. Create Command from validated data
            $command = new AssignRoleToUserCommand(
                userId: $user->id,
                roleId: (int)$validated['role_id'],
                contextLevel: $validated['context_level'],
                instanceId: (int)$validated['instance_id'],
            );

            // 3. Execute the command handler
            $this->assignRoleHandler->handle($command);

            // 4. Return a successful response
            // In CQRS, commands typically don't return data.
            // The response is a simple acknowledgment.
            return new JsonResponse([
                'message' => 'Role assignment command executed successfully.',
            ]);
        } catch (RoleNotFoundException | UserNotFoundException | InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            // Log the error in a real application: error_log($e->getMessage());
            return new JsonResponse(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
