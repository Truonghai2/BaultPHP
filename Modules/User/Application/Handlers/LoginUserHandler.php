<?php

namespace Modules\User\Application\Handlers;

use Core\Auth\AuthManager;
use Modules\User\Application\Commands\LoginUserCommand;

/**
 * Handles the logic for the LoginUserCommand.
 */
class LoginUserHandler
{
    public function __construct(private AuthManager $auth)
    {
    }

    /**
     * Attempts to authenticate a user based on the provided command.
     *
     * @return bool True if authentication is successful, false otherwise.
     */
    public function handle(LoginUserCommand $command): bool
    {
        $credentials = ['email' => $command->email, 'password' => $command->password];

        return $this->auth->guard('web')->attempt($credentials, $command->remember);
    }
}
