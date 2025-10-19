<?php

declare(strict_types=1);

namespace Modules\User\Application\Handlers;

use Core\Auth\AuthManager;
use Modules\User\Application\Commands\LoginUserCommand;
use Modules\User\Infrastructure\Models\User;

class LoginUserHandler
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {
    }

    /**
     * @return User|null The authenticated user on success, null on failure.
     */
    public function handle(LoginUserCommand $command): ?User
    {
        $credentials = [
            'email' => $command->email,
            'password' => $command->password,
        ];

        $user = $this->auth->guard('web')->attempt($credentials, $command->remember);

        return $user instanceof User ? $user : null;
    }
}
