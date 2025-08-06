<?php

declare(strict_types=1);

namespace Modules\User\Http\Controllers;

use Core\CQRS\CommandBus;
use Core\Routing\Attributes\Route;
use Modules\User\Application\Commands\DeleteUserCommand;
use Modules\User\Application\Commands\UpdateUserProfileCommand;
use Modules\User\Application\UserFinder;
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
    public function show(int $id): ResponseInterface
    {
        $userDto = $this->userFinder->findById($id);

        if (!$userDto) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($userDto);
    }

    /**
     * COMMAND: Cập nhật thông tin user. Sử dụng UseCase tập trung vào việc ghi.
     */
    #[Route('/{id}', method: 'PUT')]
    public function update(int $id, ServerRequestInterface $request): ResponseInterface
    {
        // Lấy dữ liệu từ body của request theo chuẩn PSR-7
        $data = (array) $request->getParsedBody();

        $command = new UpdateUserProfileCommand($id, $data['name'] ?? null, $data['email'] ?? null);

        $this->commandBus->dispatch($command);

        return response()->json(['message' => 'User updated successfully.']);
    }

    /**
     * COMMAND: Xóa một user.
     * Authorization được xử lý trong Handler thông qua Policy.
     */
    #[Route('/{id}', method: 'DELETE')]
    public function destroy(int $id): ResponseInterface
    {
        $this->commandBus->dispatch(new DeleteUserCommand($id));

        return response()->json(null, 204);
    }
}
