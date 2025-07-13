<?php

namespace Modules\User\Application\UseCases;

use Core\Contracts\Events\EventDispatcherInterface;
use Modules\User\Domain\Entities\User as UserEntity;
use Modules\User\Domain\Events\UserWasCreated;
use Modules\User\Domain\Repositories\UserRepositoryInterface;

class CreateUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $dispatcher
    ) {}

    public function handle(array $data): UserEntity
    {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address.");
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $userEntity = new UserEntity(null, $data['name'], $data['email'], $hashedPassword);

        $createdUser = $this->userRepository->create($userEntity);

        // Bắn event để thông báo cho các thành phần khác trong hệ thống
        $this->dispatcher->dispatch(new UserWasCreated($createdUser));

        return $createdUser;
    }
}