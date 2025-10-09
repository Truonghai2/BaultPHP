<?php

declare(strict_types=1);

namespace Modules\User\Http\Controllers;

use Core\CQRS\Command\CommandBus;
use Core\CQRS\Query\QueryBus;
use Core\Routing\Attributes\Route;
use Modules\User\Application\Commands\DeleteUserCommand;
use Modules\User\Application\Commands\UpdateUserProfileCommand;
use Modules\User\Http\Requests\UpdateUserRequest;
use Psr\Http\Message\ResponseInterface;

#[Route('/api/users')]
class UserController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    /**
     * QUERY: Lấy một user theo ID. Sử dụng QueryBus.
     */
    #[Route('/{id}', method: 'GET')]
    public function show($id): ResponseInterface
    {
        // Tạo và dispatch query
        $query = new \Modules\User\Application\Queries\FindUserByIdQuery((int)$id);
        $userDto = $this->queryBus->dispatch($query);

        if (!$userDto) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($userDto);
    }

    /**
     * COMMAND: Cập nhật thông tin user. Sử dụng UseCase tập trung vào việc ghi.
     */
    #[Route('/{id}', method: 'PUT')]
    public function update($id, UpdateUserRequest $request): ResponseInterface
    {
        // Validation is now handled automatically by injecting the UpdateUserRequest.
        $validatedData = $request->validated();

        $command = new UpdateUserProfileCommand((int)$id, $validatedData['name'] ?? null, $validatedData['email'] ?? null, $validatedData['password'] ?? null);

        $this->commandBus->dispatch($command);

        return response()->json(['message' => 'User updated successfully.']);
    }

    /**
     * COMMAND: Xóa một user.
     * Authorization được xử lý trong Handler thông qua Policy.
     */
    #[Route('/{id}', method: 'DELETE')]
    public function destroy($id): ResponseInterface
    {
        $this->commandBus->dispatch(new DeleteUserCommand((int)$id));

        return response()->json(null, 204);
    }
}
