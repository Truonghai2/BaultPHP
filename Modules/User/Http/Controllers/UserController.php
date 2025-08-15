<?php

declare(strict_types=1);

namespace Modules\User\Http\Controllers;

use Core\CQRS\CommandBus;
use Core\Routing\Attributes\Route;
use Modules\User\Application\Commands\DeleteUserCommand;
use Modules\User\Application\Commands\UpdateUserProfileCommand;
use Modules\User\Application\UserFinder;
use Modules\User\Http\Requests\UpdateUserRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[Route('/api/users')]
class UserController
{
    // Inject service Query và CommandBus chung
    public function __construct(
        private readonly UserFinder $userFinder,
        private readonly CommandBus $commandBus,
    ) {
    }

    /**
     * QUERY: Lấy một user theo ID. Sử dụng UserFinder đã được tối ưu cho việc đọc.
     */
    #[Route('/{id}', method: 'GET')]
    public function show($id): ResponseInterface
    {
        $userDto = $this->userFinder->findById((int)$id);

        if (!$userDto) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($userDto);
    }

    /**
     * COMMAND: Cập nhật thông tin user. Sử dụng UseCase tập trung vào việc ghi.
     */
    #[Route('/{id}', method: 'PUT')]
    public function update($id, ServerRequestInterface $request): ResponseInterface
    {
        // By using a FormRequest, validation is handled automatically.
        // We assume the framework makes route parameters available to the request object.
        $validatedData = (new UpdateUserRequest($request, ['id' => (int)$id]))->validated();

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
