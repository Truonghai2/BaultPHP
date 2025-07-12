<?php

namespace Modules\User\Application\UseCases;

use Core\Contracts\Events\EventDispatcherInterface;
use Modules\User\Domain\Events\UserWasCreated;
use Modules\User\Domain\Repositories\UserRepositoryInterface;
use Modules\User\Infrastructure\Models\User;

class CreateUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $dispatcher
    ) {}

    public function handle(array $data): User
    {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address.");
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $user = $this->userRepository->create($data);

        // Bắn event để thông báo cho các thành phần khác trong hệ thống
        $this->dispatcher->dispatch(new UserWasCreated($user));

        return $user;
    }
}